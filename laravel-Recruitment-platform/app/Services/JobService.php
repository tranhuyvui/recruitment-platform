<?php
// app/Services/JobService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\JobDetailModel;
use Illuminate\Support\Facades\Log;

use Exception;

class JobService
{
    // ===================== HELPERS =====================

    private function mergeJob(array $jobIds, array $rows): array
    {
        if (empty($jobIds)) return $rows;

        $mongoDetails = JobDetailModel::whereIn('mysqlJobID', $jobIds)
            ->select('mysqlJobID', 'description')
            ->get()
            ->keyBy('mysqlJobID');

        return array_map(function ($job) use ($mongoDetails) {
            $detail = $mongoDetails->get($job['JobID']);
            $job['description'] = $detail?->description ?? '';
            return $job;
        }, $rows);
    }

    private function clearJobCaches(int $employerId): void
    {
        $keys = Cache::getRedis()->keys('jobs_list:*');
        if ($keys) Cache::getRedis()->del($keys);

        $employerKeys = Cache::getRedis()->keys("employer_jobs_list:u{$employerId}:*");
        if ($employerKeys) Cache::getRedis()->del($employerKeys);
    }

    // ===================== QUERIES =====================

    public function getAllJobs(array $filters, ?int $userId): array
    {
        $page     = $filters['page'];
        $limit    = $filters['limit'];
        $offset   = ($page - 1) * $limit;

        $cacheKey = $userId
            ? "jobs_list:u{$userId}:p{$page}:l{$limit}:c{$filters['categoryId']}:loc_{$filters['location']}:min{$filters['minSalary']}:max{$filters['maxSalary']}"
            : "jobs_list:guest:p{$page}:l{$limit}:c{$filters['categoryId']}:loc_{$filters['location']}:min{$filters['minSalary']}:max{$filters['maxSalary']}";

        return Cache::remember($cacheKey, 3600, function () use ($filters, $userId, $page, $limit, $offset) {
            $where  = "WHERE j.ExpiredDate > NOW() AND j.Status = 'Approved'";
            $params = [];

            if ($filters['categoryId']) {
                $where .= " AND j.CategoryID = ?";
                $params[] = $filters['categoryId'];
            }
            if ($filters['location']) {
                $where .= " AND j.Location LIKE ?";
                $params[] = "%{$filters['location']}%";
            }
            if ($filters['minSalary'] && $filters['maxSalary']) {
                $where .= " AND j.SalaryMin >= ? AND j.SalaryMax <= ?";
                $params[] = $filters['minSalary'];
                $params[] = $filters['maxSalary'];
            }

            $total = null;
            $totalPages = null;
            if ($page === 1) {
                $count = DB::selectOne("SELECT COUNT(*) as total FROM Jobs j JOIN Employers e ON j.EmployerID = e.EmployerID JOIN Companies c ON e.CompanyID = c.CompanyID LEFT JOIN JobRecommendations r ON j.JobID = r.JobID {$where}", $params);
                $total = $count->total;
                $totalPages = ceil($total / $limit);
            }

            $rows = DB::select("
                SELECT j.JobID, j.Title, j.Location, j.CreatedAt, j.SalaryMin, j.SalaryMax, j.JobType,
                    c.CompanyName, c.LogoUrl AS CompanyLogo, j.Status, r.Score
                FROM Jobs j
                JOIN Employers e ON j.EmployerID = e.EmployerID
                JOIN Companies c ON e.CompanyID = c.CompanyID
                LEFT JOIN JobRecommendations r ON j.JobID = r.JobID AND r.CandidateID = ?
                {$where}
                ORDER BY CASE WHEN r.Score IS NOT NULL THEN r.Score END DESC, j.CreatedAt DESC
                LIMIT ? OFFSET ?
            ", [$userId, ...$params, $limit, $offset]);

            $rows   = array_map(fn($r) => (array) $r, $rows);
            $jobIds = array_column($rows, 'JobID');
            $items  = $this->mergeJob($jobIds, $rows);

            return array_filter([
                'items'      => $items,
                'total'      => $total,
                'totalPages' => $totalPages,
            ], fn($v) => $v !== null);
        });
    }

    public function getJobDetail(int $jobId): ?array
    {
        return Cache::remember("job_detail:{$jobId}", 3600, function () use ($jobId) {
            $row = DB::selectOne("
                SELECT j.JobID, j.Title, j.Location, j.CreatedAt, j.SalaryMin, j.SalaryMax,
                    j.JobType, j.Quantity, j.Status, e.EmployerID,
                    c.CompanyName, c.LogoUrl AS CompanyLogo
                FROM Jobs j
                JOIN Employers e ON j.EmployerID = e.EmployerID
                JOIN Companies c ON e.CompanyID = c.CompanyID
                WHERE j.JobID = ?
            ", [$jobId]);
            // echo "JobID: {$jobId}, Row: " . json_encode($row) . "\n";
            if (!$row) return null;

            $detail = JobDetailModel::where('mysqlJobID', $jobId)->first();
            if (!$detail) return null;

            return array_merge((array) $row, [
                'description'      => $detail->description,
                'requirements'     => $detail->requirements,
                'workingSchedule'  => $detail->workingSchedule,
                'benefits'         => $detail->benefits,
                'tags'             => $detail->tags,
                'interviewProcess' => $detail->interviewProcess,
            ]);
        });
    }

    public function createJob(int $employerId, array $data): int
    {
        $profile = DB::selectOne("SELECT ApprovalStatus FROM Employers WHERE EmployerID = ?", [$employerId]);
        if (!$profile) throw new Exception('Vui lòng tạo hồ sơ nhà tuyển dụng trước khi đăng tuyển', 400);
        if ($profile->ApprovalStatus !== 'Approved') throw new Exception('Hồ sơ nhà tuyển dụng đang chờ duyệt hoặc bị từ chối', 403);

        DB::beginTransaction();
        try {
            $expiredDate = explode('T', $data['expiredDate'])[0];

            DB::insert("
            INSERT INTO Jobs (EmployerID, CategoryID, Title, Quantity, SalaryMin, SalaryMax, Location, JobType, ExperienceRequired, ExpiredDate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
                $employerId,
                $data['categoryId'],
                $data['title'],
                $data['quantity'] ?? 1,
                $data['salaryMin'] ?? null,
                $data['salaryMax'] ?? null,
                $data['location'],
                $data['jobType'],
                $data['experienceRequired'],
                $expiredDate
            ]);
            $jobId = (int) DB::getPdo()->lastInsertId();

            $rawText = trim(preg_replace('/\s+/', ' ', "
            {$data['title']}
            Location: {$data['location']}
            Type: {$data['jobType']}
            Experience: {$data['experienceRequired']} years
            Skills: " . implode(', ', $data['tags'] ?? []) . "
            Description: {$data['description']}
            Requirements: {$data['requirements']}
        "));

            JobDetailModel::create([
                'mysqlJobID'       => $jobId,
                'description'      => $data['description'],
                'requirements'     => $data['requirements'],
                'workingSchedule'  => $data['workingSchedule'] ?? null,
                'benefits'         => $data['benefits'] ?? [],
                'tags'             => $data['tags'] ?? [],
                'interviewProcess' => $data['interviewProcess'] ?? [],
                'rowTextForAi'     => $rawText,
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->clearJobCaches($employerId);

        // ✅ Chạy sau khi response trả về, không block
        app()->terminating(function () use ($jobId, $data, $rawText) {
            try {
                $aiService = app(AIService::class);
                $aiService->processJobVector($jobId, $data, $data, $rawText);
            } catch (Exception $e) {
                Log::error("[AI-ERROR] processJobVector Job ID {$jobId}: " . $e->getMessage());
            }
        });

        return $jobId;
    }
    public function closeJob(int $employerId, int $jobId): void
    {
        $owner = DB::selectOne("SELECT EmployerID FROM Jobs WHERE JobID = ? AND EmployerID = ?", [$jobId, $employerId]);
        if (!$owner) throw new Exception('Bạn không phải người tạo công việc này', 403);

        DB::update("UPDATE Jobs SET ExpiredDate = NOW() WHERE JobID = ?", [$jobId]);
        Cache::forget("job_detail:{$jobId}");
        $this->clearJobCaches($employerId);
    }

    public function updateJob(int $employerId, int $jobId, array $data): void
    {
        $owner = DB::selectOne("SELECT EmployerID FROM Jobs WHERE JobID = ? AND EmployerID = ?", [$jobId, $employerId]);
        if (!$owner) throw new Exception('Bạn không phải là người tạo công việc này', 403);

        $status = DB::selectOne("SELECT Status FROM Jobs WHERE JobID = ?", [$jobId]);
        if ($status->Status !== 'Pending') throw new Exception('Chỉ được chỉnh sửa công việc đang ở trạng thái chờ duyệt', 400);

        // MySQL update
        $mysqlFields = [];
        $mysqlValues = [];
        foreach (['title' => 'Title', 'location' => 'Location', 'salaryMin' => 'SalaryMin', 'salaryMax' => 'SalaryMax', 'jobType' => 'JobType', 'quantity' => 'Quantity'] as $key => $col) {
            if (isset($data[$key])) {
                $mysqlFields[] = "{$col} = ?";
                $mysqlValues[] = $data[$key];
            }
        }
        if ($mysqlFields) {
            $mysqlValues[] = $jobId;
            DB::update("UPDATE Jobs SET " . implode(', ', $mysqlFields) . " WHERE JobID = ?", $mysqlValues);
        }

        // MongoDB update
        $mongoData = [];
        foreach (['description', 'requirements', 'workingSchedule', 'benefits', 'tags', 'interviewProcess'] as $field) {
            if (isset($data[$field])) $mongoData[$field] = $data[$field];
        }
        if ($mongoData) JobDetailModel::where('mysqlJobID', $jobId)->update($mongoData);

        Cache::forget("job_detail:{$jobId}");
        $this->clearJobCaches($employerId);
    }

    public function getJobOfMe(int $employerId, int $page, int $limit, string $status): array
    {
        $offset = ($page - 1) * $limit;
        $params = [$employerId];
        $where  = '';

        if ($status !== 'All') {
            if ($status === 'Expired') {
                $where = 'AND j.ExpiredDate < NOW()';
            } else {
                $where = 'AND j.Status = ? AND j.ExpiredDate >= NOW()';
                $params[] = $status;
            }
        }

        $rows = DB::select("
            SELECT j.JobID, j.Title, j.Location, j.CreatedAt, j.Status, j.ExpiredDate, j.Views,
                c.CompanyName, c.LogoUrl AS CompanyLogo,
                COUNT(ja.ApplicationID) AS ApplicationCount
            FROM Jobs j
            JOIN Employers e ON j.EmployerID = e.EmployerID
            JOIN Companies c ON e.CompanyID = c.CompanyID
            LEFT JOIN JobApplications ja ON j.JobID = ja.JobID
            WHERE e.EmployerID = ? {$where}
            GROUP BY j.JobID, j.Title, j.Location, j.CreatedAt, c.CompanyName, c.LogoUrl, j.Status, j.ExpiredDate, j.Views
            ORDER BY j.CreatedAt DESC
            LIMIT ? OFFSET ?
        ", [...$params, $limit, $offset]);

        $rows   = array_map(fn($r) => (array) $r, $rows);
        $jobIds = array_column($rows, 'JobID');
        return $this->mergeJob($jobIds, $rows);
    }

    public function getRecommendedJobs(int $candidateId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $rows = DB::select("
            SELECT j.JobID, j.Title, j.Location, j.CreatedAt, c.CompanyName, c.LogoUrl AS CompanyLogo, r.Score
            FROM JobRecommendations r
            JOIN Jobs j ON r.JobID = j.JobID
            JOIN Employers e ON j.EmployerID = e.EmployerID
            JOIN Companies c ON e.CompanyID = c.CompanyID
            WHERE r.CandidateID = ?
            ORDER BY r.Score DESC
            LIMIT ? OFFSET ?
        ", [$candidateId, $limit, $offset]);

        $rows   = array_map(fn($r) => (array) $r, $rows);
        $jobIds = array_column($rows, 'JobID');
        return $this->mergeJob($jobIds, $rows);
    }

    public function getAllCategories(): array
    {
        return Cache::remember('job_categories_list', 3600, function () {
            return DB::select("SELECT CategoryID, CategoryName FROM JobCategories");
        });
    }

    public function searchJobByCategory(int $categoryId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        return Cache::remember("jobs_category_{$categoryId}:p{$page}:l{$limit}", 3600, function () use ($categoryId, $page, $limit, $offset) {
            $total = null;
            $totalPages = null;
            if ($page === 1) {
                $count = DB::selectOne("SELECT COUNT(*) as total FROM Jobs j WHERE j.CategoryID = ? AND j.ExpiredDate > NOW() AND j.Status = 'Approved'", [$categoryId]);
                $total = $count->total;
                $totalPages = ceil($total / $limit);
            }

            $rows = DB::select("
                SELECT j.JobID, j.Title, j.Location, j.CreatedAt, c.CompanyName, c.LogoUrl AS CompanyLogo, j.Status
                FROM Jobs j
                JOIN Employers e ON j.EmployerID = e.EmployerID
                JOIN Companies c ON e.CompanyID = c.CompanyID
                WHERE j.CategoryID = ? AND j.ExpiredDate > NOW() AND j.Status = 'Approved'
                ORDER BY j.CreatedAt DESC
                LIMIT ? OFFSET ?
            ", [$categoryId, $limit, $offset]);

            $rows   = array_map(fn($r) => (array) $r, $rows);
            $jobIds = array_column($rows, 'JobID');
            $items  = $this->mergeJob($jobIds, $rows);

            return array_filter(['items' => $items, 'total' => $total, 'totalPages' => $totalPages], fn($v) => $v !== null);
        });
    }

    public function incrementJobViews(int $jobId, ?int $userId, ?string $ip): void
    {
        $viewerKey = $userId ? "job_view_user_{$userId}_{$jobId}" : "job_view_ip_{$ip}_{$jobId}";
        if (Cache::has($viewerKey)) return;

        DB::update("UPDATE Jobs SET Views = Views + 1 WHERE JobID = ?", [$jobId]);
        Cache::put($viewerKey, 1, 86400);
    }

    public function changeStatusJob(int $jobId, string $status): void
    {
        DB::update("UPDATE Jobs SET Status = ? WHERE JobID = ?", [$status, $jobId]);
        Cache::forget("job_detail:{$jobId}");
    }
    public function searchJobsByKeyword(string $q): array
    {
        return DB::table('Jobs')
            ->select('JobID')
            ->where('Title', 'like', "%{$q}%")
            ->orWhere('Location', 'like', "%{$q}%")
            ->limit(20)
            ->get()
            ->toArray();
    
    }
    public function getJobForAdmin(): array
    {
        $rows = DB::select("
            SELECT j.JobID, j.Title, j.Location, j.CreatedAt, j.SalaryMin, j.SalaryMax, j.JobType,
                c.CompanyName, c.LogoUrl AS CompanyLogo, j.Status,
                COUNT(ja.ApplicationID) AS ApplicationCount
            FROM Jobs j
            JOIN Employers e ON j.EmployerID = e.EmployerID
            JOIN Companies c ON e.CompanyID = c.CompanyID
            LEFT JOIN JobApplications ja ON j.JobID = ja.JobID
            GROUP BY j.JobID, j.Title, j.Location, j.CreatedAt, j.SalaryMin, j.SalaryMax, j.JobType, c.CompanyName, c.LogoUrl, j.Status
            ORDER BY j.CreatedAt DESC
            LIMIT 5
        ");
        $rows   = array_map(fn($r) => (array) $r, $rows);
        $jobIds = array_column($rows, 'JobID');
        return ['items' => $this->mergeJob($jobIds, $rows)];
    }

    public function getJobForAdminByStatus(int $page, int $limit, string $status): array
    {
        $offset = ($page - 1) * $limit;
        $where  = $status !== 'All' ? 'WHERE j.Status = ?' : '';
        $params = $status !== 'All' ? [$status] : [];

        $total = null;
        $totalPages = null;
        if ($page === 1) {
            $count = DB::selectOne("SELECT COUNT(*) as total FROM Jobs j JOIN Employers e ON j.EmployerID = e.EmployerID JOIN Companies c ON e.CompanyID = c.CompanyID LEFT JOIN JobApplications ja ON j.JobID = ja.JobID {$where}", $params);
            $total = $count->total;
            $totalPages = ceil($total / $limit);
        }

        $rows = DB::select("
            SELECT j.JobID, j.Title, j.Location, j.CreatedAt, j.SalaryMin, j.SalaryMax, j.JobType,
                c.CompanyName, c.LogoUrl AS CompanyLogo, j.Status,
                COUNT(ja.ApplicationID) AS ApplicationCount
            FROM Jobs j
            JOIN Employers e ON j.EmployerID = e.EmployerID
            JOIN Companies c ON e.CompanyID = c.CompanyID
            LEFT JOIN JobApplications ja ON j.JobID = ja.JobID
            {$where}
            GROUP BY j.JobID, j.Title, j.Location, j.CreatedAt, j.SalaryMin, j.SalaryMax, j.JobType, c.CompanyName, c.LogoUrl, j.Status
            ORDER BY j.CreatedAt DESC
            LIMIT ? OFFSET ?
        ", [...$params, $limit, $offset]);

        $rows   = array_map(fn($r) => (array) $r, $rows);
        $jobIds = array_column($rows, 'JobID');
        $items  = $this->mergeJob($jobIds, $rows);
        return array_filter(['items' => $items, 'total' => $total, 'totalPages' => $totalPages], fn($v) => $v !== null);
    }

    public function getMonthlyStats(): array
    {
        $jobStats = DB::selectOne("
            SELECT
                COUNT(CASE WHEN YEAR(CreatedAt) = YEAR(CURDATE()) AND MONTH(CreatedAt) = MONTH(CURDATE()) THEN 1 END) AS currentMonth,
                COUNT(CASE WHEN YEAR(CreatedAt) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(CreatedAt) = MONTH(CURDATE() - INTERVAL 1 MONTH) THEN 1 END) AS lastMonth
            FROM Jobs
        ");
        $employerStats = DB::selectOne("
            SELECT
                COUNT(CASE WHEN YEAR(CreatedAt) = YEAR(CURDATE()) AND MONTH(CreatedAt) = MONTH(CURDATE()) THEN 1 END) AS currentMonth,
                COUNT(CASE WHEN YEAR(CreatedAt) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(CreatedAt) = MONTH(CURDATE() - INTERVAL 1 MONTH) THEN 1 END) AS lastMonth
            FROM Users WHERE Role = 'Employer'
        ");

        return [
            'jobStats'      => $this->calcPercent((array) $jobStats),
            'employerStats' => $this->calcPercent((array) $employerStats),
        ];
    }

    public function get7DayStats(): array
    {
        $rows = DB::select("
            SELECT DATE(CreatedAt) AS date, COUNT(*) AS count
            FROM Jobs
            WHERE CreatedAt >= CURDATE() - INTERVAL 6 DAY
            GROUP BY DATE(CreatedAt)
            ORDER BY DATE(CreatedAt) ASC
        ");

        $statsMap = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $statsMap[$date] = 0;
        }
        foreach ($rows as $row) {
            $statsMap[$row->date] = $row->count;
        }

        return array_map(fn($date, $count) => compact('date', 'count'), array_keys($statsMap), $statsMap);
    }

    private function calcPercent(array $stats): array
    {
        $current = $stats['currentMonth'] ?? 0;
        $last    = $stats['lastMonth'] ?? 0;
        $percent = $last === 0 ? ($current > 0 ? 100 : 0) : round(($current - $last) / $last * 100, 1);
        return ['currentMonth' => $current, 'lastMonth' => $last, 'percentChange' => $percent];
    }
    public function getJobsByIds(array $jobIds): array
    {
        if (empty($jobIds)) {
            return [];
        }

        $jobs = DB::table('Jobs as j')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->whereIn('j.JobID', $jobIds)
            ->select([
                'j.JobID',
                'j.Title',
                'j.Location',
                'j.CreatedAt',
                'j.Status',
                'c.CompanyName',
                'c.LogoUrl as CompanyLogo'
            ])
            ->get();
        $mongoDetails = JobDetailModel::query()
            ->whereIn('mysqlJobID', $jobIds)
            ->get([
                'mysqlJobID',
                'description',
                'requirements',
                'tags'
            ]);

        $detailMap = $mongoDetails->keyBy('mysqlJobID');

        $finalJobList = $jobs->map(function ($job) use ($detailMap) {

            $detail = $detailMap->get($job->JobID);

            return [
                'JobID' => $job->JobID,
                'Title' => $job->Title,
                'Location' => $job->Location,
                'CreatedAt' => $job->CreatedAt,
                'Status' => $job->Status,
                'CompanyName' => $job->CompanyName,
                'CompanyLogo' => $job->CompanyLogo,

                'description' => $this->cleanText(
                    $detail->description ?? ''
                ),

                'requirements' => $detail->requirements ?? '',
                'tags' => $detail->tags ?? [],
            ];
        });

        return $finalJobList->toArray();
    }
    private function cleanText(string $text): string
    {
        return strip_tags($text);
    }
}
