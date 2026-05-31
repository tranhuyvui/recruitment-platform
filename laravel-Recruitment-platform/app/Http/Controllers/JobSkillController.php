<?php

namespace App\Http\Controllers;

use App\Services\JobSkillService;
use App\Http\Requests\JobSkillRequest;
use Exception;

class JobSkillController extends Controller
{
    protected JobSkillService $jobSkillService;

    public function __construct(JobSkillService $jobSkillService)
    {
        $this->jobSkillService = $jobSkillService;
    }

    public function getJobSkills(JobSkillRequest $request, $jobId)
    {
        try {
            $skills = $this->jobSkillService->getSkillsByJobId($jobId);
            
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách kỹ năng của Job thành công!',
                'data' => $skills
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function syncJobSkills(JobSkillRequest $request, $jobId)
    {
        try {
            if ($request->user()->Role !== 'Employer') {
                throw new Exception("Lỗi phân quyền: Chỉ Nhà tuyển dụng mới được cập nhật kỹ năng cho bài đăng!", 403);
            }

            $skillIds = $request->input('skillIds'); 
            
            $this->jobSkillService->syncJobSkills($jobId, $skillIds);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật kỹ năng cho bài đăng thành công!'
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function removeJobSkill(JobSkillRequest $request, $jobId, $skillId)
    {
        try {
            if ($request->user()->Role !== 'Employer') {
                throw new Exception("Lỗi phân quyền: Chỉ Nhà tuyển dụng mới được xóa kỹ năng khỏi bài đăng!", 403);
            }

            $this->jobSkillService->removeSkillFromJob($jobId, $skillId);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa kỹ năng khỏi Job!'
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(Exception $e)
    {
        $code = is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600 ? $e->getCode() : 500;
        return response()->json(['success' => false, 'message' => $e->getMessage()], $code);
    }
}