<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\ResumeService;

class JobApplicationService
{
    protected ResumeService $resumeService;
    public function getApplicationByJobId(int $jobID, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        return DB::table('JobApplications as app')
            ->join('Candidates as can', 'app.CandidateID', '=', 'can.CandidateID')
            ->select([
                'app.ApplicationID',
                'can.FullName',
                'can.ExperienceYears',
                'can.AvatarUrl',
                'app.MatchScore',
                'app.Status',
                'app.CreatedAt',
            ])
            ->where('app.JobID', $jobID)
            ->orderByDesc('app.MatchScore')
            ->orderByDesc('app.CreatedAt')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }
    public function getApplicationDetail(int $applicationID): ?array
    {
        $app = DB::table('JobApplications as a')
            ->join('Candidates as c', 'a.CandidateID', '=', 'c.CandidateID')
            ->join('Users as u', 'u.UserID', '=', 'c.CandidateID')
            ->join('Jobs as j', 'a.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->join('Companies as co', 'e.CompanyID', '=', 'co.CompanyID')
            ->select([
                'a.ApplicationID',
                'a.Status',
                'a.CreatedAt',
                'a.MatchScore',
                'a.AI_Summary_Review',
                'a.ResumeID',
                'u.Email',
                'c.CandidateID',
                'c.FullName',
                'c.Phone',
                'c.AvatarUrl',
                DB::raw('j.Title as JobTitle'),
                'j.SalaryMin',
                'j.SalaryMax',
                'co.CompanyName',
            ])
            ->where('a.ApplicationID', $applicationID)
            ->first();

        if (!$app) {
            return null;
        }

        return [
            'ApplicationID' => $app->ApplicationID,
            'FullName' => $app->FullName,
            'Phone' => $app->Phone,
            'Email' => $app->Email,
            'AvatarUrl' => $app->AvatarUrl,
            'Status' => $app->Status,
            'CreatedAt' => $app->CreatedAt,
            'MatchScore' => $app->MatchScore,
            'AI_Summary_Review' => $app->AI_Summary_Review,
            'ResumeID' => $app->ResumeID,
            'ResumeDetail' => $this->resumeService->getResumeDetailByResumeID((int) $app->ResumeID),

            'JobTitle' => $app->JobTitle,
            'CompanyName' => $app->CompanyName,
            'SalaryMin' => $app->SalaryMin,
            'SalaryMax' => $app->SalaryMax,
        ];
    }

    public function updateApplicationStatus(int $applicationID, int $userID, string $status): void
    {
        DB::transaction(function () use ($applicationID, $userID, $status) {
            $data = $this->getApplicationWithPermission($applicationID, $userID);

            if ($data->Status === $status) {
                return;
            }

            if ($status === 'Accepted') {
                $this->checkJobCapacity((int) $data->JobID, (int) $data->Quantity);
            }

            $affectedRows = DB::table('JobApplications')
                ->where('ApplicationID', $applicationID)
                ->update([
                    'Status' => $status
                ]);

            if ($affectedRows === 0) {
                throw new \Exception('Application không tồn tại', 404);
            }
        });
    }

    private function getApplicationWithPermission(int $applicationID, int $userID): object
    {
        $data = DB::table('JobApplications as ja')
            ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->select([
                'ja.Status',
                'ja.JobID',
                'j.Quantity',
            ])
            ->where('ja.ApplicationID', $applicationID)
            ->where('c.CreatedBy', $userID)
            ->lockForUpdate()
            ->first();

        if (!$data) {
            throw new \Exception('Không tìm thấy đơn hoặc bạn không có quyền', 403);
        }

        return $data;
    }

    private function checkJobCapacity(int $jobID, int $quantity): void
    {
        $data = DB::table('JobApplications')
            ->selectRaw('COUNT(*) AS acceptedCount')
            ->where('JobID', $jobID)
            ->where('Status', 'Accepted')
            ->lockForUpdate()
            ->first();

        $acceptedCount = (int) $data->acceptedCount;

        if ($acceptedCount >= $quantity) {
            throw new \Exception('Đã đủ số lượng tuyển', 400);
        }
    }
    public function getSubmittedApplications(int $candidateID, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
    
        return DB::table('JobApplications as ja')
            ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->join('Companies as co', 'e.CompanyID', '=', 'co.CompanyID')
            ->select([
                'co.CompanyID',
                'co.CompanyName',
                'j.JobID',
                DB::raw('j.Title as JobTitle'),
                DB::raw('ja.Status as ApplicationStatus'),
                'j.ExpiredDate',
                'ja.CreatedAt',
                'ja.ApplicationID',
            ])
            ->where('ja.CandidateID', $candidateID)
            ->orderByDesc('ja.CreatedAt')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }
    public function createJobApplication(int $jobID, int $candidateID, int $resumeID): int
    {
        try {
            $this->checkJob($jobID);
    
            $this->checkResume($resumeID, $candidateID);
    
            $this->checkConflict($jobID, $candidateID);
    
            $applicationID = DB::table('JobApplications')->insertGetId([
                'JobID' => $jobID,
                'CandidateID' => $candidateID,
                'ResumeID' => $resumeID,
                'Status' => 'Pending',
                'MatchScore' => 0,
                'AI_Summary_Review' => null,
                'CreatedAt' => now(),
            ]);
    
            return (int) $applicationID;
    
        } catch (\Throwable $e) {
            throw $e;
        }
    }
    private function checkConflict(int $jobID, int $candidateID): void
    {
        $exists = DB::table('JobApplications')
            ->where('JobID', $jobID)
            ->where('CandidateID', $candidateID)
            ->exists();

        if ($exists) {
            throw new \Exception('Bạn đã ứng tuyển công việc này rồi', 409);
        }
    }
    private function checkResume(int $resumeID, int $candidateID): void
    {
        $exists = DB::table('Resumes')
            ->where('ResumeID', $resumeID)
            ->where('CandidateID', $candidateID)
            ->exists();
    
        if (!$exists) {
            throw new \Exception('CV không tồn tại hoặc không thuộc về bạn', 400);
        }
    }
    private function checkJob(int $jobID): void
    {
        $job = DB::table('Jobs')
            ->select([
                'JobID',
                'Quantity',
                'ExpiredDate',
            ])
            ->where('JobID', $jobID)
            ->first();
    
        if (!$job) {
            throw new \Exception('Công việc không tồn tại', 404);
        }
    
        if ($job->ExpiredDate && date('Y-m-d', strtotime($job->ExpiredDate)) < now()->toDateString()) {
            throw new \Exception('Công việc đã hết hạn ứng tuyển', 400);
        }
    }
    public function updateApplicationStatusCandidate(int $applicationID, int $candidateID, string $status): void
    {
        DB::transaction(function () use ($applicationID, $candidateID, $status) {
            $oldStatus = $this->getStatusJobApplication($applicationID, $candidateID);
    
            if ($oldStatus !== 'Pending') {
                throw new \Exception('Chỉ có thể huỷ đơn đang chờ xử lý', 400);
            }
    
            if ($status !== 'Cancelled') {
                throw new \Exception('Candidate chỉ được huỷ đơn', 403);
            }
    
            $affectedRows = DB::table('JobApplications')
                ->where('ApplicationID', $applicationID)
                ->where('CandidateID', $candidateID)
                ->update([
                    'Status' => $status
                ]);
    
            if ($affectedRows === 0) {
                throw new \Exception('Cập nhật thất bại', 500);
            }
        });
    }
    private function getStatusJobApplication(int $applicationID, int $candidateID): string
    {
        $application = DB::table('JobApplications')
            ->select('Status')
            ->where('ApplicationID', $applicationID)
            ->where('CandidateID', $candidateID)
            ->lockForUpdate()
            ->first();
    
        if (!$application) {
            throw new \Exception('Không tìm thấy đơn ứng tuyển', 404);
        }
    
        return $application->Status;
    }
    public function getJobIdByApplicationId(int $applicationID): int
    {
        $application = DB::table('JobApplications')
            ->select('JobID')
            ->where('ApplicationID', $applicationID)
            ->first();
    
        if (!$application) {
            throw new \Exception('Application không tồn tại', 404);
        }
    
        return (int) $application->JobID;
    }
    public function analyzeApplicationWithAI(int $applicationID, int $jobID, int $resumeID): void
    {
        $job = $this->getJobDetail($jobID);
    
        if (!$job) {
            throw new \Exception("Job {$jobID} not found");
        }
    
        $cv = $this->resumeService->getResumeDetailByResumeID($resumeID);
    
        if (!$cv) {
            throw new \Exception("CV not found for resume {$resumeID}");
        }
    
        $dataAI = $this->analyzeAI($job, $cv);
    
        if (
            !$dataAI ||
            !isset($dataAI['MatchScore']) ||
            !is_numeric($dataAI['MatchScore']) ||
            $dataAI['MatchScore'] < 0 ||
            $dataAI['MatchScore'] > 100 ||
            empty($dataAI['AI_Summary_Review'])
        ) {
            throw new \Exception("Invalid AI result for Application {$applicationID}");
        }
    
        $this->updateApplicationAI(
            $applicationID,
            (int) $dataAI['MatchScore'],
            $dataAI['AI_Summary_Review']
        );
    }
    
    private function analyzeAI(array $job, array $cv): array
    {
        $prompt = "
        Bạn là một AI hỗ trợ tuyển dụng nhân sự (HR AI Assistant).
        
        Nhiệm vụ của bạn là đánh giá mức độ phù hợp giữa CV của ứng viên và mô tả công việc.
        
        =====================
        MÔ TẢ CÔNG VIỆC
        =====================
        {$this->formatJob($job)}
        
        =====================
        CV ỨNG VIÊN
        =====================
        {$this->formatCV($cv)}
        
        =====================
        NGUYÊN TẮC ĐÁNH GIÁ
        =====================
        - Đánh giá mức độ phù hợp tổng thể giữa ứng viên và công việc.
        - Xem xét các yếu tố: kỹ năng, kinh nghiệm, học vấn và mức độ liên quan đến vị trí.
        - MatchScore phải là số từ 0 đến 100.
        - Đánh giá khách quan, nhất quán và dựa trên thông tin thực tế.
        - Không đánh giá cao nếu ứng viên thiếu các kỹ năng cốt lõi của công việc.
        - Nếu ứng viên thiếu kỹ năng bắt buộc, MatchScore không nên vượt quá 60.
        - Ưu tiên kinh nghiệm thực tế và dự án liên quan trực tiếp.
        
        =====================
        YÊU CẦU QUAN TRỌNG
        =====================
        1. Chỉ trả về JSON hợp lệ.
        2. Không sử dụng markdown.
        3. Không thêm bất kỳ nội dung nào ngoài JSON.
        4. Trường AI_Summary_Review phải viết bằng tiếng Việt.
        5. Nội dung nhận xét gồm 2–3 câu ngắn gọn.
        6. Văn phong chuyên nghiệp, dễ hiểu đối với nhà tuyển dụng.
        7. Các giá trị số phải là kiểu number.
        
        =====================
        ĐỊNH DẠNG KẾT QUẢ
        =====================
        {
            \"MatchScore\": number,
            \"AI_Summary_Review\": \"Nhận xét ngắn gọn 2-3 câu bằng tiếng Việt mô tả mức độ phù hợp của ứng viên với công việc.\"
        }
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
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]
        );
    
        if (!$response->successful()) {
            throw new \Exception('AI không trả về kết quả đánh giá', $response->status());
        }
    
        $text = $response->json('candidates.0.content.parts.0.text');
    
        if (!$text) {
            throw new \Exception('AI không trả về nội dung đánh giá', 500);
        }
    
        $json = json_decode($text, true);
    
        if (!is_array($json)) {
            throw new \Exception('AI trả về JSON không hợp lệ', 500);
        }
    
        return $json;
    }
    
    private function updateApplicationAI(int $applicationID, int $matchScore, string $summaryReview): void
    {
        DB::table('JobApplications')
            ->where('ApplicationID', $applicationID)
            ->update([
                'MatchScore' => $matchScore,
                'AI_Summary_Review' => $summaryReview,
            ]);
    }
    
    
    private function formatJob(array $job): string
    {
        $benefits = $job['Benefits'] ?? [];
        $tags = $job['Tags'] ?? [];
    
        if (is_array($benefits)) {
            $benefits = implode("\n", array_map(fn ($b) => "- {$b}", $benefits));
        }
    
        if (is_array($tags)) {
            $tags = implode(", ", $tags);
        }
    
        return "
            JOB TITLE:
            " . ($job['Title'] ?? '') . "
            
            COMPANY:
            " . ($job['CompanyName'] ?? '') . "
            
            DESCRIPTION:
            " . ($job['Description'] ?? '') . "
            
            REQUIREMENTS:
            " . ($job['Requirements'] ?? '') . "
            
            WORKING SCHEDULE:
            " . ($job['WorkingSchedule'] ?? '') . "
            
            BENEFITS:
            {$benefits}
            
            TAGS:
            {$tags}
            
            RAW TEXT FOR AI:
            " . ($job['RawTextForAi'] ?? '') . "
            ";
    }
    
    private function formatCV(array $cv): string
    {
        $skills = $cv['skills'] ?? [];
        $experience = $cv['experience'] ?? [];
        $education = $cv['education'] ?? [];
        $projects = $cv['projects'] ?? [];
    
        if (is_array($skills)) {
            $skills = collect($skills)->map(function ($skill) {
                if (is_array($skill)) {
                    $name = $skill['skillName'] ?? '';
                    $level = $skill['level'] ?? 'N/A';
                    return "- {$name} ({$level})";
                }
    
                return "- {$skill}";
            })->implode("\n");
        }
    
        if (is_array($experience)) {
            $experience = collect($experience)->map(function ($exp) {
                return "
                    - Công ty: " . ($exp['companyName'] ?? '') . "
                    Vị trí: " . ($exp['position'] ?? '') . "
                    Thời gian: " . ($exp['startDate'] ?? '') . " - " . ($exp['endDate'] ?? 'Hiện tại') . "
                    Mô tả: " . ($exp['description'] ?? '') . "
                    ";
                            })->implode("\n");
                        }
                    
                        if (is_array($education)) {
                            $education = collect($education)->map(function ($edu) {
                                return "
                    - Trường: " . ($edu['institution'] ?? '') . "
                    Bằng cấp: " . ($edu['degree'] ?? '') . "
                    Chuyên ngành: " . ($edu['major'] ?? '') . "
                    GPA: " . ($edu['gpa'] ?? '') . "
                    ";
            })->implode("\n");
        }
    
        if (is_array($projects)) {
            $projects = collect($projects)->map(function ($project) {
                $technologies = $project['technologies'] ?? [];
    
                if (is_array($technologies)) {
                    $technologies = implode(", ", $technologies);
                }
    
                return "
                    - Dự án: " . ($project['projectName'] ?? '') . "
                    Vai trò: " . ($project['role'] ?? '') . "
                    Công nghệ: {$technologies}
                    Link: " . ($project['link'] ?? '') . "
                    Mô tả: " . ($project['description'] ?? '') . "
                    ";
            })->implode("\n");
        }
    
        return "
        RESUME TITLE:
        " . ($cv['title'] ?? '') . "
        
        SUMMARY:
        " . ($cv['summary'] ?? '') . "
        
        SKILLS:
        {$skills}
        
        EXPERIENCE:
        {$experience}
        
        EDUCATION:
        {$education}
        
        PROJECTS:
        {$projects}
        ";
    }
    public function getChartStats(int $userID, string $type): array
    {
        $company = DB::table('Companies')
            ->select('CompanyID')
            ->where('CreatedBy', $userID)
            ->first();
    
        if (!$company) {
            throw new \Exception('Bạn không có công ty', 404);
        }
    
        $companyID = (int) $company->CompanyID;
    
        $categories = [];
        $data = [];
    
        if ($type === 'week') {
            $rows = DB::table('JobApplications as ja')
                ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
                ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
                ->selectRaw('DAYOFWEEK(ja.CreatedAt) as day, COUNT(*) as total')
                ->where('e.CompanyID', $companyID)
                ->whereRaw('YEARWEEK(ja.CreatedAt, 1) = YEARWEEK(CURRENT_DATE(), 1)')
                ->groupBy('day')
                ->get();
    
            $map = [
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
                6 => 0,
                7 => 0,
                1 => 0,
            ];
    
            foreach ($rows as $row) {
                $map[(int) $row->day] = (int) $row->total;
            }
    
            $categories = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
            $data = [
                $map[2],
                $map[3],
                $map[4],
                $map[5],
                $map[6],
                $map[7],
                $map[1],
            ];
        }
    
        if ($type === 'month') {
            $rows = DB::table('JobApplications as ja')
                ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
                ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
                ->selectRaw("
                    WEEK(ja.CreatedAt, 1) 
                    - WEEK(DATE_SUB(ja.CreatedAt, INTERVAL DAYOFMONTH(ja.CreatedAt)-1 DAY), 1) 
                    + 1 as week,
                    COUNT(*) as total
                ")
                ->where('e.CompanyID', $companyID)
                ->whereRaw('MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE())')
                ->whereRaw('YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE())')
                ->groupBy('week')
                ->get();
    
            $map = [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
            ];
    
            foreach ($rows as $row) {
                $map[(int) $row->week] = (int) $row->total;
            }
    
            $categories = ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4'];
            $data = [
                $map[1],
                $map[2],
                $map[3],
                $map[4],
            ];
        }
    
        if ($type === 'year') {
            $rows = DB::table('JobApplications as ja')
                ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
                ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
                ->selectRaw('MONTH(ja.CreatedAt) as month, COUNT(*) as total')
                ->where('e.CompanyID', $companyID)
                ->whereRaw('YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE())')
                ->groupBy('month')
                ->get();
    
            $map = [];
    
            for ($i = 1; $i <= 12; $i++) {
                $map[$i] = 0;
            }
    
            foreach ($rows as $row) {
                $map[(int) $row->month] = (int) $row->total;
            }
    
            $categories = [
                'T1', 'T2', 'T3', 'T4', 'T5', 'T6',
                'T7', 'T8', 'T9', 'T10', 'T11', 'T12'
            ];
    
            $data = array_values($map);
        }
    
        return [
            'categories' => $categories,
            'data' => $data,
        ];
    }
    //////////////////////////////////////////////
    private function getJobDetail(int $jobID): ?array
    {
        // Mock tạm để test AI
        return [
            'JobID' => $jobID,
            'Title' => 'Frontend Developer ReactJS',
            'CompanyName' => 'Công ty test',
            'Description' => 'Phát triển giao diện web bằng ReactJS, phối hợp với backend để tích hợp API.',
            'Requirements' => 'Có kinh nghiệm ReactJS, HTML, CSS, JavaScript.',
            'WorkingSchedule' => 'Thứ 2 - Thứ 6',
            'Benefits' => [
                'Thưởng dự án',
                'Laptop công ty',
                'Du lịch hàng năm',
            ],
            'Tags' => [
                'ReactJS',
                'Frontend',
                'JavaScript',
            ],
            'RawTextForAi' => 'Frontend Developer ReactJS Location: Quận 1, TP.HCM Type: Full-time Experience: 2 years Skills: ReactJS, Frontend, JavaScript Description: Phát triển giao diện web bằng ReactJS, phối hợp với backend để tích hợp API. Requirements: Có kinh nghiệm ReactJS, HTML, CSS, JavaScript.',
            'SalaryMin' => 10000000,
            'SalaryMax' => 20000000,
            'Quantity' => 3,
        ];
    }
    
/////////////////////////////////////////////
    
}