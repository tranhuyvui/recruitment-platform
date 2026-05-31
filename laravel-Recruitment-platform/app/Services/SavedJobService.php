<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SavedJobService
{
    public function savedJob(int $candidateID, int $jobID): bool
    {
        DB::table('SavedJobs')->insert([
            'CandidateID' => $candidateID,
            'JobID' => $jobID,
            'SavedAt' => now(),
        ]);

        return true;
    }

    public function removeSavedJob(int $candidateID, int $jobID): bool
    {
        $affectedRows = DB::table('SavedJobs')
            ->where('CandidateID', $candidateID)
            ->where('JobID', $jobID)
            ->delete();

        return $affectedRows > 0;
    }

    public function isSavedJob(int $candidateID, int $jobID): bool
    {
        return DB::table('SavedJobs')
            ->where('CandidateID', $candidateID)
            ->where('JobID', $jobID)
            ->exists();
    }

    public function getSavedJobs(int $candidateID, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $response = [];

        if ($page === 1) {
            $total = DB::table('SavedJobs as sj')
                ->join('Jobs as j', 'sj.JobID', '=', 'j.JobID')
                ->where('sj.CandidateID', $candidateID)
                ->count();

            $response['total'] = $total;
            $response['totalPages'] = (int) ceil($total / $limit);
        }

        $rows = DB::table('SavedJobs as sj')
            ->join('Jobs as j', 'sj.JobID', '=', 'j.JobID')
            ->join('Employers as e', 'j.EmployerID', '=', 'e.EmployerID')
            ->join('Companies as c', 'e.CompanyID', '=', 'c.CompanyID')
            ->select([
                'j.JobID',
                'j.Title',
                'j.Location',
                'j.CreatedAt',
                'j.SalaryMin',
                'j.SalaryMax',
                'c.CompanyName',
                DB::raw('c.LogoUrl as CompanyLogo'),
                DB::raw('sj.SavedAt as SavedAt'),
            ])
            ->where('sj.CandidateID', $candidateID)
            ->orderByDesc('sj.SavedAt')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        $response['items'] = $rows;

        return $response;
    }
}