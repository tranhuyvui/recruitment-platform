<?php

namespace App\Services;

use App\Models\ResumeModel;
use App\Models\ResumeDetailMongoModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\CandidateService;

class ResumeService
{
    protected CandidateService $candidateService;
    public function buildManualResume(int $candidateId, array $resumeData, array $candidate): array
    {
        $newResumeId = null;
        $mongoCreated = false;

        DB::beginTransaction();

        try {
            $this->candidateService->upsertCandidateProfile($candidate);
            $resume = ResumeModel::query()->create([
                'CandidateID' => $candidateId,
                'Title' => $resumeData['title'] ?? 'CV Chưa Đặt Tên',
                'ResumeFileUrl' => $resumeData['ResumeFileUrl'] ?? null,
                'TemplateID' => $resumeData['templateId'] ?? 1,
                'Summary' => $resumeData['summary'] ?? null,
                'IsAnalyzed' => true,
                'CreatedAt' => now(),
            ]);

            $newResumeId = (int) $resume->ResumeID;

            ResumeDetailMongoModel::query()->create([
                'resumeId' => $newResumeId,
                'templateId' => $resumeData['templateId'] ?? 1,
                'title' => $resumeData['title'] ?? 'CV Chưa Đặt Tên',
                'AvatarUrl' => $resumeData['AvatarUrl'] ?? null,
                'summary' => $resumeData['summary'] ?? null,
                'skills' => $this->normalizeArrayField($resumeData['skills'] ?? []),
                'experience' => $this->normalizeArrayField($resumeData['experience'] ?? []),
                'education' => $this->normalizeArrayField($resumeData['education'] ?? []),
                'projects' => $this->normalizeArrayField($resumeData['projects'] ?? []),
            ]);

            $mongoCreated = true;

            if (!empty($resumeData['skills']) && is_array($resumeData['skills'])) {
                $this->candidateService->appendSkillsFromResume($candidateId, $resumeData['skills']);
            }

            DB::commit();

            return [
                'resumeId' => $newResumeId
            ];

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($mongoCreated && $newResumeId) {
                ResumeDetailMongoModel::query()
                    ->where('resumeId', $newResumeId)
                    ->delete();
            }

            throw $e;
        }
    }
    public function getListResume(int $candidateId): array
    {
        return DB::table('Candidates as c')
            ->join('Resumes as r', 'c.CandidateID', '=', 'r.CandidateID')
            ->select([
                'r.ResumeID',
                'r.Title',
                'r.CreatedAt',
                'c.AvatarUrl',
            ])
            ->where('c.CandidateID', $candidateId)
            ->get()
            ->toArray();
    }
    public function getCandidateResumes(int $candidateId): array
    {
        return ResumeModel::query()
            ->select([
                'ResumeID',
                'Title',
                'Summary',
                'TemplateID',
                'IsAnalyzed',
                'CreatedAt',
            ])
            ->where('CandidateID', $candidateId)
            ->orderByDesc('CreatedAt')
            ->get()
            ->toArray();
    }
    public function getResumeDetail(int $resumeId, int $candidateId): ?array
    {
        $resumeExists = ResumeModel::query()
            ->where('ResumeID', $resumeId)
            ->where('CandidateID', $candidateId)
            ->exists();
    
        if (!$resumeExists) {
            throw new \Exception('Cv không tồn tại', 404);
        }
    
        $detail = ResumeDetailMongoModel::query()
            ->where('resumeId', $resumeId)
            ->first();
    
        return $detail ? $detail->toArray() : null;
    }
    public function getResumeDetailByResumeID(int $resumeId): ?array
    {
        $resumeExists = ResumeModel::query()
            ->where('ResumeID', $resumeId)
            ->exists();
    
        if (!$resumeExists) {
            throw new \Exception('Cv không tồn tại', 404);
        }
    
        $detail = ResumeDetailMongoModel::query()
            ->where('resumeId', $resumeId)
            ->first();
    
        return $detail ? $detail->toArray() : null;
    }
    public function getResumeForEmployer(int $resumeId): ?array
    {
        $detail = ResumeDetailMongoModel::query()
            ->where('resumeId', $resumeId)
            ->first();
    
        return $detail ? $detail->toArray() : null;
    }
    public function deleteResume(int $resumeId, int $candidateId): bool
    {
        DB::beginTransaction();
    
        try {
            $affectedRows = ResumeModel::query()
                ->where('ResumeID', $resumeId)
                ->where('CandidateID', $candidateId)
                ->delete();
    
            if ($affectedRows === 0) {
                DB::rollBack();
                return false;
            }
    
            ResumeDetailMongoModel::query()
                ->where('resumeId', $resumeId)
                ->delete();
    
            DB::commit();
    
            return true;
    
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updateManualResume(int $candidateId, int $resumeId, array $resumeData): ?array
    {
        DB::beginTransaction();
    
        try {
            $affectedRows = ResumeModel::query()
                ->where('ResumeID', $resumeId)
                ->where('CandidateID', $candidateId)
                ->update([
                    'Title' => $resumeData['title'],
                    'Summary' => $resumeData['summary'] ?? null,
                    'TemplateID' => $resumeData['templateId'] ?? 1,
                ]);
    
            if ($affectedRows === 0) {
                DB::rollBack();
                return null;
            }
    
            ResumeDetailMongoModel::query()
                ->where('resumeId', $resumeId)
                ->update([
                    'templateId' => $resumeData['templateId'] ?? 1,
                    'title' => $resumeData['title'],
                    'summary' => $resumeData['summary'] ?? null,
                    'skills' => $this->normalizeArrayField($resumeData['skills'] ?? []),
                    'experience' => $this->normalizeArrayField($resumeData['experience'] ?? []),
                    'education' => $this->normalizeArrayField($resumeData['education'] ?? []),
                    'projects' => $this->normalizeArrayField($resumeData['projects'] ?? []),
                ]);
    
            $skills = $this->normalizeArrayField($resumeData['skills'] ?? []);

            if (!empty($skills)) {
                $this->candidateService->updateCandidateSkills($candidateId, $skills);
            }
    
            DB::commit();
    
            return ResumeDetailMongoModel::query()
                ->where('resumeId', $resumeId)
                ->first()
                ?->toArray();
    
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function generateResumeSummary(array $resumeData): string
    {
        try {
            $targetTitle = $resumeData['targetTitle'] ?? 'Nhân viên';
    
            $skills = $this->normalizeArrayField($resumeData['skills'] ?? []);
            $experience = $this->normalizeArrayField($resumeData['experience'] ?? []);
            $education = $this->normalizeArrayField($resumeData['education'] ?? []);
            $projects = $this->normalizeArrayField($resumeData['projects'] ?? []);
    
            $skillsText = !empty($skills)
                ? collect($skills)->map(function ($skill) {
                    if (is_array($skill)) {
                        return $skill['skillName'] ?? '';
                    }
    
                    return (string) $skill;
                })->filter()->implode(', ')
                : 'Chưa cập nhật';
    
            $experienceText = 'Chưa có kinh nghiệm thực tế (Fresher/Intern).';
    
            if (!empty($experience)) {
                $experienceText = collect($experience)->map(function ($exp) {
                    if (!is_array($exp)) {
                        return "- {$exp}";
                    }
    
                    return '- ' . ($exp['position'] ?? '') .
                        ' tại ' . ($exp['companyName'] ?? '') .
                        ' (' . ($exp['description'] ?? 'Không có mô tả') . ')';
                })->implode("\n");
            }
    
            $educationText = 'Chưa cập nhật';
    
            if (!empty($education)) {
                $educationText = collect($education)->map(function ($edu) {
                    if (!is_array($edu)) {
                        return "- {$edu}";
                    }
    
                    return '- ' . ($edu['degree'] ?? '') .
                        ' ngành ' . ($edu['major'] ?? '') .
                        ' tại ' . ($edu['institution'] ?? '');
                })->implode("\n");
            }
    
            $projectsText = 'Chưa cập nhật dự án.';
    
            if (!empty($projects)) {
                $projectsText = collect($projects)->map(function ($project) {
                    if (!is_array($project)) {
                        return "- {$project}";
                    }
    
                    $technologies = $project['technologies'] ?? [];
    
                    if (is_array($technologies)) {
                        $technologies = implode(', ', $technologies);
                    }
    
                    return '- Dự án: ' . ($project['projectName'] ?? '') .
                        ' | Vai trò: ' . ($project['role'] ?? '') .
                        ' | Công nghệ: ' . $technologies;
                })->implode("\n");
            }
    
            $prompt = "
                Bạn là một chuyên gia tuyển dụng (HR Director) tài ba.
                Nhiệm vụ của bạn là viết MỘT đoạn văn ngắn khoảng 3-4 câu, dưới 100 chữ để làm phần Tóm tắt mục tiêu nghề nghiệp cho ứng viên ứng tuyển vị trí: {$targetTitle}.
                
                YÊU CẦU NGHIÊM NGẶT:
                - Giọng văn: Chuyên nghiệp, tự tin, mang danh xưng ngôi thứ nhất tôi.
                - Nêu bật được thế mạnh cốt lõi, tập trung vào việc đáp ứng được vị trí {$targetTitle}.
                - Trả về ĐÚNG MỘT ĐOẠN VĂN, không có tiêu đề, không có gạch đầu dòng, không giải thích gì thêm.
                
                DỮ LIỆU CỦA ỨNG VIÊN:
                * Kỹ năng: {$skillsText}
                * Học vấn:
                {$educationText}
                * Kinh nghiệm:
                {$experienceText}
                * Dự án nổi bật:
                {$projectsText}
                ";
    
            $apiKey = env('GEMINI_API_KEY');
    
            if (!$apiKey) {
                throw new \Exception('Thiếu GEMINI_API_KEY trong file .env', 500);
            }
    
            $response = Http::timeout(60)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
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
                throw new \Exception('Hệ thống AI đang quá tải, bạn chịu khó tự gõ tóm tắt nhé!', 500);
            }
    
            $text = $response->json('candidates.0.content.parts.0.text');
    
            if (!$text) {
                throw new \Exception('Hệ thống AI đang quá tải, bạn chịu khó tự gõ tóm tắt nhé!', 500);
            }
    
            return trim($text);
    
        } catch (\Throwable $e) {
            throw new \Exception('Hệ thống AI đang quá tải, bạn chịu khó tự gõ tóm tắt nhé!', 500);
        }
    }
    private function normalizeArrayField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}