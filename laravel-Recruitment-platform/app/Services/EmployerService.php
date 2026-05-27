<?php

namespace App\Services;

use App\Models\EmployerModel;
use Illuminate\Support\Facades\DB;

class EmployerService
{
    public function createEmployer(array $data): int
    {
        $exists = $this->checkEmployerID((int) $data['EmployerID']);

        if ($exists) {
            throw new \Exception('Bạn đã gửi yêu cầu rồi!', 409);
        }

        $employer = EmployerModel::query()->create([
            'EmployerID' => $data['EmployerID'],
            'CompanyID' => $data['CompanyID'],
            'Position' => $data['Position'],
            'ApprovalStatus' => $data['ApprovalStatus'] ?? 'Pending',
        ]);

        return (int) $employer->EmployerID;
    }

    public function checkEmployerID(int $employerID): bool
    {
        return EmployerModel::query()
            ->where('EmployerID', $employerID)
            ->exists();
    }

    public function checkEmployerProfile(int $employerID): ?array
    {
        $employer = EmployerModel::query()
            ->where('EmployerID', $employerID)
            ->first();

        return $employer ? $employer->toArray() : null;
    }

    public function checkCompanyStatus(int $employerID): bool
    {
        $company = DB::table('Employers as e')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->select('c.Status')
            ->where('e.EmployerID', $employerID)
            ->first();

        if (!$company) {
            throw new \Exception('Không tìm thấy công ty', 404);
        }

        if ($company->Status === 'Approved') {
            return true;
        }

        if ($company->Status === 'Pending') {
            throw new \Exception('Hồ sơ công ty đang chờ xét duyệt', 403);
        }

        if ($company->Status === 'Rejected') {
            throw new \Exception('Hồ sơ công ty đã bị từ chối', 403);
        }

        if ($company->Status === 'Banned') {
            throw new \Exception('Tài khoản công ty đã bị khóa', 403);
        }

        throw new \Exception('Trạng thái công ty không hợp lệ', 500);
    }

    public function updateEmployer(int $companyID, string $position): bool
    {
        $affectedRows = EmployerModel::query()
            ->where('CompanyID', $companyID)
            ->update([
                'Position' => $position
            ]);

        if ($affectedRows === 0) {
            throw new \Exception('Công ty không tồn tại hoặc đã bị xóa', 404);
        }

        return true;
    }

    public function updateStatusEmployer(int $employerID, int $userID, string $approvalStatus): bool
    {
        $affectedRows = DB::table('Employers as e')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->where('e.EmployerID', $employerID)
            ->where('c.CreatedBy', $userID)
            ->update([
                'e.ApprovalStatus' => $approvalStatus
            ]);

        if ($affectedRows === 0) {
            throw new \Exception('Không có quyền hoặc nhân viên không tồn tại', 404);
        }

        return true;
    }

    public function getPendingEmployers(int $userID, string $status): array
    {
        $company = $this->getCompanyByUser($userID);

        if (!$company) {
            throw new \Exception('Bạn không phải người tạo công ty', 403);
        }

        $query = DB::table('Employers as e')
            ->join('Users as u', 'u.UserID', '=', 'e.EmployerID')
            ->select([
                'e.EmployerID',
                'u.Email',
                'e.Position',
                'e.ApprovalStatus',
            ])
            ->where('e.CompanyID', $company->CompanyID)
            ->where('e.EmployerID', '!=', $userID);

        if ($status !== 'all') {
            $query->where('e.ApprovalStatus', $status);
        }

        return $query->get()->toArray();
    }

    public function getCompanyByUser(int $userID): ?object
    {
        return DB::table('Companies')
            ->select('CompanyID')
            ->where('CreatedBy', $userID)
            ->first();
    }

    private function getJobStats(int $companyID): object
    {
        return DB::table('Jobs as j')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->selectRaw("
                COUNT(CASE 
                    WHEN MONTH(j.CreatedAt) = MONTH(CURRENT_DATE()) 
                    AND YEAR(j.CreatedAt) = YEAR(CURRENT_DATE()) 
                    THEN 1 END) AS nowCount,

                COUNT(CASE 
                    WHEN MONTH(j.CreatedAt) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
                    AND YEAR(j.CreatedAt) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                    THEN 1 END) AS prevCount
            ")
            ->where('e.CompanyID', $companyID)
            ->first();
    }

    private function getApplicationStats(int $companyID): object
    {
        return DB::table('JobApplications as ja')
            ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->selectRaw("
                COUNT(CASE 
                    WHEN MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE()) 
                    AND YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE()) 
                    THEN 1 END) AS nowCount,

                COUNT(CASE 
                    WHEN MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
                    AND YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                    THEN 1 END) AS prevCount
            ")
            ->where('e.CompanyID', $companyID)
            ->first();
    }

    private function getHiredStats(int $companyID): object
    {
        return DB::table('JobApplications as ja')
            ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->selectRaw("
                COUNT(CASE 
                    WHEN ja.Status = 'Accepted'
                    AND MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE()) 
                    AND YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE()) 
                    THEN 1 END) AS nowCount,

                COUNT(CASE 
                    WHEN ja.Status = 'Accepted'
                    AND MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
                    AND YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                    THEN 1 END) AS prevCount
            ")
            ->where('e.CompanyID', $companyID)
            ->first();
    }

    private function getRejectedStats(int $companyID): object
    {
        return DB::table('JobApplications as ja')
            ->join('Jobs as j', 'ja.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->selectRaw("
                COUNT(CASE 
                    WHEN ja.Status = 'Rejected'
                    AND MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE()) 
                    AND YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE()) 
                    THEN 1 END) AS nowCount,

                COUNT(CASE 
                    WHEN ja.Status = 'Rejected'
                    AND MONTH(ja.CreatedAt) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
                    AND YEAR(ja.CreatedAt) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                    THEN 1 END) AS prevCount
            ")
            ->where('e.CompanyID', $companyID)
            ->first();
    }

    private function calcTrend(int $now, int $prev): array
    {
        if ($prev === 0 && $now === 0) {
            return [
                'value' => 0,
                'percentage' => 0,
                'trendUp' => true,
            ];
        }

        if ($prev === 0) {
            return [
                'value' => $now,
                'percentage' => 100,
                'trendUp' => true,
            ];
        }

        $percent = round((($now - $prev) / $prev) * 100);

        return [
            'value' => $now,
            'percentage' => abs($percent),
            'trendUp' => $percent >= 0,
        ];
    }

    public function getDashboardStats(int $userID): array
    {
        $company = $this->getCompanyByUser($userID);

        if (!$company) {
            throw new \Exception('Bạn không có công ty', 404);
        }

        $companyID = (int) $company->CompanyID;

        $job = $this->getJobStats($companyID);
        $app = $this->getApplicationStats($companyID);
        $hired = $this->getHiredStats($companyID);
        $rejected = $this->getRejectedStats($companyID);

        return [
            'jobs' => $this->calcTrend((int) $job->nowCount, (int) $job->prevCount),
            'applications' => $this->calcTrend((int) $app->nowCount, (int) $app->prevCount),
            'hired' => $this->calcTrend((int) $hired->nowCount, (int) $hired->prevCount),
            'rejected' => $this->calcTrend((int) $rejected->nowCount, (int) $rejected->prevCount),
        ];
    }

    public function getTopEmployers(): array
    {
        return DB::table('Employers as e')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->join('Jobs as j', 'j.EmployerID', '=', 'e.EmployerID')
            ->select([
                'c.CompanyName',
                DB::raw('c.City as Location'),
                'c.LogoUrl',
                DB::raw('COUNT(j.JobID) as JobCount'),
            ])
            ->where('e.ApprovalStatus', 'Approved')
            ->groupBy('c.CompanyName', 'c.City', 'c.LogoUrl')
            ->orderByDesc('JobCount')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function getLogoTopEmployers(): array
    {
        return DB::table('Employers as e')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->join('Jobs as j', 'j.EmployerID', '=', 'e.EmployerID')
            ->select('c.LogoUrl')
            ->where('e.ApprovalStatus', 'Approved')
            ->groupBy('c.CompanyName', 'c.City', 'c.LogoUrl')
            ->orderByDesc(DB::raw('COUNT(j.JobID)'))
            ->limit(10)
            ->pluck('c.LogoUrl')
            ->toArray();
    }

    public function getAllEmployers(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $response = [];

        if ($page === 1) {
            $total = DB::table('Users as u')
                ->join('Employers as e', 'u.UserID', '=', 'e.EmployerID')
                ->leftJoin('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
                ->where('u.Role', 'Employer')
                ->count();

            $response['total'] = $total;
            $response['totalpage'] = (int) ceil($total / $limit);
        }

        $items = DB::table('Users as u')
            ->join('Employers as e', 'u.UserID', '=', 'e.EmployerID')
            ->leftJoin('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->select([
                DB::raw('u.UserID as EmployerID'),
                'u.Email',
                'e.Position',
                DB::raw('u.Status as UserStatus'),
                'c.CompanyName',
                'c.LogoUrl',
                'c.Industry',
                DB::raw('e.ApprovalStatus as EmployerStatus'),
            ])
            ->where('u.Role', 'Employer')
            ->orderByDesc('u.CreatedAt')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        $response['items'] = $items;

        return $response;
    }
}