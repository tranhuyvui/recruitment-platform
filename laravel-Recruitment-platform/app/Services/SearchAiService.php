<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SearchAiService
{
    public function recommendJobsByAI(array $resume, int $candidateId): void
    {
        $resumeText = $this->buildResumeText($resume);

        $resumeVector = $this->generateEmbedding($resumeText);

        $matches = $this->queryPineconeJobs($resumeVector);

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

    private function generateEmbedding(string $text): array
    {
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            throw new \Exception('Thiếu GEMINI_API_KEY trong file .env', 500);
        }

        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}",
            [
                'model' => 'models/gemini-embedding-001',
                'content' => [
                    'parts' => [
                        [
                            'text' => $text
                        ]
                    ]
                ]
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Không tạo được embedding từ Gemini', 500);
        }

        $values = $response->json('embedding.values');

        if (!$values || !is_array($values)) {
            throw new \Exception('Gemini không trả về embedding hợp lệ', 500);
        }

        return $values;
    }

    private function queryPineconeJobs(array $vector): array
    {
        $apiKey = env('PINECONE_API_KEY');
        $indexHost = env('PINECONE_INDEX_HOST');

        if (!$apiKey || !$indexHost) {
            throw new \Exception('Thiếu PINECONE_API_KEY hoặc PINECONE_INDEX_HOST trong file .env', 500);
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$indexHost}/query", [
                'vector' => $vector,
                'topK' => 10,
                'includeMetadata' => true,
                'filter' => [
                    'type' => [
                        '$eq' => 'job'
                    ]
                ]
            ]);

        if (!$response->successful()) {
            throw new \Exception('Không query được Pinecone', 500);
        }

        return $response->json('matches') ?? [];
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
}