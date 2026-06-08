<?php
// app/Services/AiService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


use Exception;

class AIService
{
    private string $geminiApiKey;
    private string $pineconeApiKey;
    private string $pineconeHost;

    public function __construct()
    {
        $this->geminiApiKey   = env('GEMINI_API_KEY');
        $this->pineconeApiKey = env('PINECONE_API_KEY');
        $this->pineconeHost   = env('PINECONE_HOST');
    }

    // ===================== GEMINI =====================

    public function expandQuery(string $q): string
    {
        try {
            $prompt = "Bạn là chuyên gia phân tích ý định tìm kiếm việc làm.
            Nhiệm vụ: Chuyển câu lệnh tìm kiếm của người dùng thành một danh sách các từ khóa, kỹ năng và ngành nghề liên quan để tìm kiếm trong database.
            
            Quy tắc đặc biệt:
            - Nếu người dùng tìm kiếm phủ định (ví dụ: \"không phải code\", \"phi kỹ thuật\"), hãy liệt kê các ngành nghề KHÔNG liên quan đến lập trình như: \"Lao động phổ thông, Marketing, Nhân sự, F&B, Chăm sóc khách hàng, Làm vườn\".
            - Nếu người dùng tìm kiếm kỹ thuật, hãy mở rộng như bình thường.
            
            Từ khóa từ người dùng: \"{$q}\"
            
            Chỉ trả về chuỗi các từ khóa mở rộng, cách nhau bằng dấu phẩy. Không giải thích gì thêm.";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiApiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ]
            ]);

            if (!$response->successful()) return $q;

            return $response->json('candidates.0.content.parts.0.text') ?? $q;
        } catch (Exception $e) {
            Log::error('expandQuery lỗi: ' . $e->getMessage());
            return $q;
        }
    }

    public function generateEmbedding(string $text): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$this->geminiApiKey}", [
            'model'   => 'models/gemini-embedding-001',
            'content' => [
                'parts' => [['text' => $text]]
            ]
        ]);

        if (!$response->successful()) {
            throw new Exception('Lỗi khi tạo embedding: ' . $response->body());
        }

        return $response->json('embedding.values') ?? [];
    }

    // ===================== PINECONE =====================

    public function upsertVector(string $id, array $values, array $metadata): void
    {
        $response = Http::withHeaders([
            'Api-Key'      => $this->pineconeApiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->pineconeHost}/vectors/upsert", [
            'vectors' => [
                [
                    'id'       => $id,
                    'values'   => $values,
                    'metadata' => $metadata,
                ]
            ]
        ]);

        if (!$response->successful()) {
            throw new Exception('Lỗi khi upsert Pinecone: ' . $response->body());
        }
    }

    public function queryVector(array $vector, int $topK = 10, array $filter = []): array
    {
        $body = [
            'vector'          => $vector,
            'topK'            => $topK,
            'includeMetadata' => true,
        ];
        if ($filter) $body['filter'] = $filter;

        $response = Http::withHeaders([
            'Api-Key'      => $this->pineconeApiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->pineconeHost}/query", $body);

        if (!$response->successful()) {
            throw new Exception('Lỗi khi query Pinecone: ' . $response->body());
        }

        return $response->json('matches') ?? [];
    }

    public function searchJobsByAI(string $searchQuery, int $topK = 10): array
    {
        $expandedQuery = $this->expandQuery($searchQuery);
        $queryVector   = $this->generateEmbedding($expandedQuery);

        $matches = $this->queryVector($queryVector, $topK, [
            'type' => ['$eq' => 'job']
        ]);

        $minScore = 0.73;
        return array_filter($matches, function ($match) use ($minScore) {
            $score   = $match['score'] ?? 0;
            $id      = $match['id'] ?? '';
            return $score >= $minScore && $id !== '' && is_numeric($id);
        });
    }

    // ===================== PROCESS JOB VECTOR =====================

    public function processJobVector(int $jobId, array $job, array $jobDetail, string $rawText): void
    {
        try {
            $vector = $this->generateEmbedding($rawText);

            $this->upsertVector(
                (string) $jobId,
                $vector,
                [
                    'type'         => 'job',
                    'jobId'        => $jobId,
                    'location'     => $job['location'] ?? '',
                    'title'        => $job['title'] ?? '',
                    'description'  => $jobDetail['description'] ?? '',
                    'requirements' => $jobDetail['requirements'] ?? '',
                    'tags'         => $jobDetail['tags'] ?? [],
                    'benefits'     => implode(', ', $jobDetail['benefits'] ?? []),
                ]
            );

            Log::info("[AI-LOG] Đã index thành công Job ID: {$jobId}");
        } catch (Exception $e) {
            Log::error("[AI-ERROR] Job ID {$jobId}: " . $e->getMessage());
        }
    }
    public function rerankWithAI(string $query, array $jobs): array
    {
        try {
            $jobContext = array_map(function ($job, $index) {

                $requirements = $job['requirements'] ?? '';

                return [
                    'index' => $index,
                    'title' => $job['Title'] ?? $job['title'] ?? '',
                    'tags' => $job['tags'] ?? [],
                    'requirements' => mb_substr($requirements, 0, 300),
                ];
            }, $jobs, array_keys($jobs));

            $prompt = "
                Bạn là chuyên gia lọc hồ sơ. Người dùng tìm kiếm: \"{$query}\".

                Dựa vào danh sách JSON dưới đây:
                " . json_encode($jobContext, JSON_UNESCAPED_UNICODE) . "

                NHIỆM VỤ:
                1. Chỉ giữ lại những công việc thực sự liên quan đến ngành nghề/kỹ năng trong từ khóa tìm kiếm.
                2. LOẠI BỎ các công việc khác ngành.
                3. Trả về mảng JSON chứa index của các job phù hợp, ưu tiên job tốt nhất lên đầu.

                TRẢ VỀ DUY NHẤT MẢNG JSON.
                Ví dụ:
                [0,1]
                ";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiApiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ]
                ]
            );

            if (!$response->successful()) {
                return $jobs;
            }

            $text = $response->json('candidates.0.content.parts.0.text', '');

            preg_match('/\[(\s*\d+\s*,?\s*)*\]/', $text, $matches);

            if (empty($matches[0])) {
                return $jobs;
            }

            $sortedIndexes = json_decode($matches[0], true);

            if (!is_array($sortedIndexes)) {
                return $jobs;
            }

            return collect($sortedIndexes)
                ->map(fn($index) => $jobs[$index] ?? null)
                ->filter()
                ->values()
                ->toArray();
        } catch (\Exception $e) {

            Log::error('rerankWithAI lỗi: ' . $e->getMessage());

            return $jobs;
        }
    }
    private function saveJobRecommendations(int $candidateId, array $matches): void
    {
        $validMatches = collect($matches)
            ->filter(function ($item) {
                $jobId = $item['metadata']['jobId'] ?? null;

                return $jobId !== null && is_numeric($jobId);
            })
            ->values();

        foreach ($validMatches as $item) {
            DB::table('JobRecommendations')->updateOrInsert(
                [
                    'CandidateID' => $candidateId,
                    'JobID' => (int) $item['metadata']['jobId'],
                ],
                [
                    'Score' => $item['score'] ?? 0,
                    'RecommendedAt' => now(),
                ]
            );
        }
    }
    public function recommendJobsByAI(array $resume, int $candidateId): void
    {
        $resumeText = $this->buildResumeText($resume);

        $resumeVector = $this->generateEmbedding($resumeText);

        $matches = $this->queryVector($resumeVector, 10, [
            'type' => ['$eq' => 'job']
        ]);

        $this->saveJobRecommendations($candidateId, $matches);
    }
    private function buildResumeText(array $resume): string
    {
        $skills = [];

        if (!empty($resume['skills']) && is_array($resume['skills'])) {
            $skills = collect($resume['skills'])
                ->map(function ($skill) {
                    if (is_array($skill)) {
                        return strtolower($skill['skillName'] ?? '');
                    }

                    return strtolower((string) $skill);
                })
                ->filter()
                ->toArray();
        }

        $technologies = [];

        if (!empty($resume['projects']) && is_array($resume['projects'])) {
            foreach ($resume['projects'] as $project) {
                if (!is_array($project)) {
                    continue;
                }

                $projectTechnologies = $project['technologies'] ?? [];

                if (is_array($projectTechnologies)) {
                    foreach ($projectTechnologies as $tech) {
                        $technologies[] = strtolower((string) $tech);
                    }
                }
            }
        }

        $experienceDesc = '';

        if (!empty($resume['experience']) && is_array($resume['experience'])) {
            $experienceDesc = collect($resume['experience'])
                ->map(function ($exp) {
                    if (!is_array($exp)) {
                        return '';
                    }

                    return ($exp['position'] ?? '') . ' ' . ($exp['description'] ?? '');
                })
                ->filter()
                ->implode(' ');
        }

        $totalExp = !empty($resume['experience']) && is_array($resume['experience'])
            ? count($resume['experience'])
            : 0;

        $allSkills = array_merge($skills, $technologies);

        $text = "
            " . ($resume['title'] ?? '') . "

            Type: full-time

            Experience: {$totalExp} years

            Skills: " . implode(', ', $allSkills) . "

            Description:
            " . ($resume['summary'] ?? '') . " {$experienceDesc}

            Requirements:
            " . implode(', ', $skills) . "
        ";

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
