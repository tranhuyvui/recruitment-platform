<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Exception;

class SkillService
{
    public function getAllSkills()
    {
        $cachedSkills = Redis::get('all_skills');
        if ($cachedSkills) {
            return json_decode($cachedSkills); 
        }

        $skills = DB::table('Skills')->orderBy('SkillName', 'asc')->get();

        if ($skills->isNotEmpty()) {
            Redis::setex('all_skills', 3600, json_encode($skills));
        }

        return $skills;
    }

    public function getSkillById($skillId)
    {
        return DB::table('Skills')->where('SkillID', $skillId)->first();
    }

    public function deleteSkill($skillId)
    {
        $deleted = DB::table('Skills')->where('SkillID', $skillId)->delete();
        
        if ($deleted) {
            Redis::del('all_skills'); 
        }
        
        return $deleted > 0;
    }

    public function createSkill($skillName)
    {
        $cleanName = trim($skillName);

        $exists = DB::table('Skills')->where('SkillName', $cleanName)->exists();
        if ($exists) {
            throw new Exception("Kỹ năng '{$cleanName}' đã có trong hệ thống rồi sếp ơi!", 400);
        }

        $insertId = DB::table('Skills')->insertGetId(['SkillName' => $cleanName]);
        
        Redis::del('all_skills');
        
        return ['SkillID' => $insertId, 'SkillName' => $cleanName];
    }

    public function updateSkill($skillId, $newSkillName)
    {
        $cleanName = trim($newSkillName);

        $exists = DB::table('Skills')
            ->where('SkillName', $cleanName)
            ->where('SkillID', '!=', $skillId)
            ->exists();
            
        if ($exists) {
            throw new Exception("Tên '{$cleanName}' bị trùng với một kỹ năng khác rồi!", 400);
        }

        // Update
        $updated = DB::table('Skills')->where('SkillID', $skillId)->update(['SkillName' => $cleanName]);
        
        if ($updated) {
            Redis::del('all_skills'); 
        }
        
        return $updated > 0;
    }
}