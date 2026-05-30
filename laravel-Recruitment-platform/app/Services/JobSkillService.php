<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class JobSkillService
{
    public function getSkillsByJobId($jobId)
    {
        return DB::table('JobSkills as js')
            ->join('Skills as s', 'js.SkillID', '=', 's.SkillID')
            ->where('js.JobID', $jobId)
            ->select('s.SkillID', 's.SkillName')
            ->get();
    }

    public function syncJobSkills($jobId, array $skillIds)
    {
        DB::transaction(function () use ($jobId, $skillIds) {
            
            DB::table('JobSkills')->where('JobID', $jobId)->delete();

            if (!empty($skillIds)) {
                $insertData = [];
                foreach ($skillIds as $skillId) {
                    $insertData[] = [
                        'JobID' => $jobId, 
                        'SkillID' => $skillId
                    ];
                }
                
                DB::table('JobSkills')->insert($insertData);
            }
        });

        return true;
    }

    public function removeSkillFromJob($jobId, $skillId)
    {
        return DB::table('JobSkills')
            ->where('JobID', $jobId)
            ->where('SkillID', $skillId)
            ->delete();
    }
}