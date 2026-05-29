<?php

namespace App\Services;

use App\Models\CandidateDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;
use Carbon\Carbon;

class CandidateService
{
    public function upsertCandidateProfile(array $data)
    {
        return DB::table('Candidates')->upsert(
            [
                'CandidateID' => $data['CandidateID'],
                'FullName' => $data['FullName'],
                'Phone' => $data['Phone'] ?? null,
                'DateOfBirth' => $data['DateOfBirth'] ?? null,
                'Address' => $data['Address'] ?? null,
                'AvatarUrl' => $data['AvatarUrl'] ?? null,
            ],
            ['CandidateID'], 
            ['FullName', 'Phone', 'DateOfBirth', 'Address', 'AvatarUrl']
        );
    }

    public function upsertCandidateDetailMongo(int $candidateId, array $data)
    {
        $updateFields = [];
        if (isset($data['experience'])) $updateFields['experience'] = $data['experience'];
        if (isset($data['education'])) $updateFields['education'] = $data['education'];
        if (isset($data['projects'])) $updateFields['projects'] = $data['projects'];

        return CandidateDetail::updateOrCreate(
            ['candidateId' => $candidateId],
            $updateFields
        );
    }

    public function getCandidateProfile(int $userId)
    {
        $sqlProfile = DB::table('Users as u')
            ->join('Candidates as c', 'u.UserID', '=', 'c.CandidateID')
            ->where('u.UserID', $userId)
            ->select('u.Email', 'u.Role', 'u.Status', 'c.*')
            ->first();

        if (!$sqlProfile) return null;

        $mongoDetail = CandidateDetail::where('candidateId', $userId)->first();

        return array_merge((array) $sqlProfile, [
            'experience' => $mongoDetail->experience ?? [],
            'education' => $mongoDetail->education ?? [],
            'projects' => $mongoDetail->projects ?? [],
        ]);
    }

    public function getCandidateInfo(int $candidateId)
    {
        return DB::table('Candidates as c')
            ->join('Users as u', 'c.CandidateID', '=', 'u.UserID')
            ->where('c.CandidateID', $candidateId)
            ->select('c.CandidateID', 'c.FullName', 'c.Phone', 'c.DateOfBirth', 'c.Address', 'c.AvatarUrl', 'u.Email')
            ->first();
    }


    public function getCandidateSkills(int $userId)
    {
        return DB::table('CandidateSkills as cs')
            ->join('Skills as s', 'cs.SkillID', '=', 's.SkillID')
            ->where('cs.CandidateID', $userId)
            ->select('s.SkillID', 's.SkillName', 'cs.SkillLevel')
            ->get()->toArray();
    }

    public function analyzeTextWithAI(string $rawText)
    {
        $allSkills = DB::table('Skills')->get();
        $dictionary = $allSkills->map(fn($s) => ['id' => $s->SkillID, 'name' => $s->SkillName])->toArray();

        $dictionaryJson = json_encode($dictionary, JSON_UNESCAPED_UNICODE);

        $prompt = <<<EOT
                Bạn là một Trưởng phòng Nhân sự cấp cao, cực kỳ nghiêm túc và chuyên nghiệp. Dưới đây là đoạn văn bản ứng viên mô tả kỹ năng của họ:
                "{$rawText}"
                
                Và đây là danh sách CÁC KỸ NĂNG CHUẨN đang có trong hệ thống database của tôi:
                {$dictionaryJson}
                
                Nhiệm vụ của bạn:
                1. Trích xuất TẤT CẢ các kỹ năng CHUYÊN MÔN NGHỀ NGHIỆP từ đoạn văn bản trên.
                2. TUYỆT ĐỐI BỎ QUA và loại trừ các từ ngữ tào lao, sở thích cá nhân, hoặc thói quen không phục vụ cho công việc chuyên môn (ví dụ: nhậu, ngủ, chơi game, lười biếng, chửi thề...). Nếu đoạn văn không chứa bất kỳ kỹ năng công việc nào hợp lệ, hãy trả về mảng rỗng [].
                3. Đối chiếu với danh sách chuẩn. Nếu khớp (kể cả đồng nghĩa/viết tắt), hãy lấy 'id' chuẩn.
                4. QUAN TRỌNG: Nếu ứng viên có một kỹ năng CHUYÊN MÔN mới hoàn toàn (không có trong danh sách), hãy trích xuất nó và gán 'id' là chuỗi "new".
                5. CHỈ trả về một mảng JSON với cấu trúc object. TUYỆT ĐỐI không trả về chữ hay giải thích thêm.
                
                Ví dụ định dạng trả về chuẩn:
                [
                    { "id": 1, "name": "Digital Marketing" },
                    { "id": 5, "name": "English" },
                    { "id": "new", "name": "Livestream TikTok" }
                ]
                EOT;

        $apiKey = env('GEMINI_API_KEY');
        
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
            'contents' => [['parts' => [['text' => $prompt]]]]
        ]);

        if (!$response->successful()) {
            throw new Exception("AI_PARSE_ERROR", 500);
        }

        $textResponse = $response->json('candidates.0.content.parts.0.text') ?? "";
        $cleanedText = trim(str_replace(['```json', '```'], '', $textResponse));
        $parsedResults = json_decode($cleanedText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("AI_PARSE_ERROR", 500);
        }

        return array_map(function ($item) use ($allSkills) {
            if ($item['id'] === 'new') {
                return ['isNew' => true, 'skillId' => null, 'skillName' => $item['name']];
            } else {
                $dbSkill = $allSkills->firstWhere('SkillID', $item['id']);
                return [
                    'isNew' => false,
                    'skillId' => $item['id'],
                    'skillName' => $dbSkill ? $dbSkill->SkillName : $item['name']
                ];
            }
        }, $parsedResults);
    }

    private function ensureSkillsExist(array $skillsToSave)
    {
        $finalSkillIdsToSave = [];
        
        foreach ($skillsToSave as $skill) {
            $currentSkillId = $skill['skillId'] ?? null;
            $isNew = $skill['isNew'] ?? false;

            if ($isNew || !$currentSkillId) {
                $cleanName = trim($skill['skillName']);
                $existing = DB::table('Skills')->where('SkillName', $cleanName)->first();

                if ($existing) {
                    $currentSkillId = $existing->SkillID;
                } else {
                    $currentSkillId = DB::table('Skills')->insertGetId(['SkillName' => $cleanName]);
                }
            }

            if ($currentSkillId) {
                $finalSkillIdsToSave[] = [
                    'id' => $currentSkillId,
                    'level' => $skill['level'] ?? 'Intermediate'
                ];
            }
        }
        return $finalSkillIdsToSave;
    }

    private function syncCandidateSkills(int $userId, array $finalSkillIdsToSave)
    {
        $wantedSkillIds = array_column($finalSkillIdsToSave, 'id');

        if (!empty($wantedSkillIds)) {
            DB::table('CandidateSkills')
                ->where('CandidateID', $userId)
                ->whereNotIn('SkillID', $wantedSkillIds)
                ->delete();
        } else {
            DB::table('CandidateSkills')->where('CandidateID', $userId)->delete();
        }

        foreach ($finalSkillIdsToSave as $item) {
            DB::table('CandidateSkills')->updateOrInsert(
                ['CandidateID' => $userId, 'SkillID' => $item['id']],
                ['SkillLevel' => $item['level']]
            );
        }
    }

    public function updateCandidateSkills(int $userId, array $skillsToSave)
    {
        $finalSkills = $this->ensureSkillsExist($skillsToSave);
        $this->syncCandidateSkills($userId, $finalSkills);
    }

    public function saveSkillsTransaction(int $userId, array $skillsToSave)
    {
        DB::transaction(function () use ($userId, $skillsToSave) {
            $this->updateCandidateSkills($userId, $skillsToSave);
        });
    }

    public function appendSkillsFromResume(int $userId, array $skillsToSave)
    {
        DB::transaction(function () use ($userId, $skillsToSave) {
            $finalSkills = $this->ensureSkillsExist($skillsToSave);
            
            foreach ($finalSkills as $item) {
                DB::table('CandidateSkills')->updateOrInsert(
                    ['CandidateID' => $userId, 'SkillID' => $item['id']],
                    ['SkillLevel' => $item['level']]
                );
            }
        });
    }


    public function getCandidatesListForEmployer()
    {
        return DB::table('Candidates')
            ->join('Users', 'Candidates.CandidateID', '=', 'Users.UserID') 
            ->select('Candidates.CandidateID', 'Candidates.FullName', 'Candidates.AvatarUrl', 'Candidates.Phone', 'Users.Email') 
            ->orderBy('Candidates.CandidateID', 'desc')
            ->get();
    }

    public function getAllCandidates(int $page, int $limit)
    {
        $offset = ($page - 1) * $limit;

        $total = DB::table('Candidates')->count();
        $totalpage = ceil($total / $limit);

        $items = DB::table('Candidates as c')
            ->join('Users as u', 'c.CandidateID', '=', 'u.UserID')
            ->where('u.Role', 'Candidate')
            ->select('c.CandidateID', 'c.FullName', 'c.AvatarUrl', 'c.Phone', 'c.CreatedAt', 'c.DateOfBirth', 'c.Address', 'u.Email', 'u.Status')
            ->orderByDesc('c.CandidateID')
            ->limit($limit)
            ->offset($offset)
            ->get()->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'totalpage' => $totalpage
        ];
    }

    public function getMonthlyNewCandidates()
    {
        $query = "
            SELECT 
                COUNT(CASE
                    WHEN YEAR(CreatedAt) = YEAR(CURDATE())
                    AND MONTH(CreatedAt) = MONTH(CURDATE())
                    THEN 1 END) As currentMonth,

                COUNT(CASE
                    WHEN YEAR(CreatedAt) = YEAR(CURDATE() - INTERVAL 1 MONTH)
                    AND MONTH(CreatedAt) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                    THEN 1 END) As lastMonth 
            FROM Users WHERE Role = 'Candidate'
        ";
        
        $row = (array) DB::selectOne($query);
        
        $currentMonth = $row['currentMonth'] ?? 0;
        $lastMonth = $row['lastMonth'] ?? 0;

        $percentageChange = $lastMonth === 0 
            ? ($currentMonth > 0 ? 100 : 0) 
            : (($currentMonth - $lastMonth) / $lastMonth) * 100;

        return [
            'currentMonth' => $currentMonth,
            'lastMonth' => $lastMonth,
            'percentChange' => round($percentageChange, 1)
        ];
    }

    public function get7DayCandidateStats()
    {
        $query = "
            SELECT
                DATE(CreatedAt) AS date,
                COUNT(*) AS count
            FROM Candidates
            WHERE CreatedAt >= CURDATE() - INTERVAL 6 DAY
            GROUP BY DATE(CreatedAt)
            ORDER BY DATE(CreatedAt) ASC      
        ";
        $rows = DB::select($query);

        $statsMap = [];
        for ($i = 0; $i < 7; $i++) {
            $dateString = Carbon::now()->subDays($i)->format('Y-m-d');
            $statsMap[$dateString] = 0;
        }

        foreach ($rows as $row) {
            $statsMap[$row->date] = $row->count;
        }

        $result = [];
        foreach ($statsMap as $date => $count) {
            $result[] = ['date' => $date, 'count' => $count];
        }
        return $result;
    }
}