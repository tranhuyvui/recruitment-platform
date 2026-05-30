<?php

namespace App\Http\Controllers;

use App\Services\SkillService;
use App\Http\Requests\SkillRequest;
use Exception;

class SkillController extends Controller
{
    protected SkillService $skillService;

    public function __construct(SkillService $skillService)
    {
        $this->skillService = $skillService;
    }

    public function getAllSkills(SkillRequest $request)
    {
        try {
            $skills = $this->skillService->getAllSkills();
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách kỹ năng thành công',
                'data' => $skills
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getSkillById(SkillRequest $request, $id)
    {
        try {
            $skill = $this->skillService->getSkillById($id);
            if (!$skill) throw new Exception("Không tìm thấy kỹ năng này!", 404);

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin kỹ năng thành công',
                'data' => $skill
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function createSkill(SkillRequest $request) 
    {
        try {
            $newSkill = $this->skillService->createSkill($request->input('skillName'));
            
            return response()->json([
                'success' => true,
                'message' => 'Thêm kỹ năng thành công',
                'data' => $newSkill
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateSkill(SkillRequest $request, $id)
    {
        try {
            $isUpdated = $this->skillService->updateSkill($id, $request->input('skillName'));
            if (!$isUpdated) throw new Exception("Không tìm thấy kỹ năng để cập nhật!", 404);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật kỹ năng thành công'
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function deleteSkill(SkillRequest $request, $id)
    {
        try {
            $isDeleted = $this->skillService->deleteSkill($id);
            if (!$isDeleted) throw new Exception("Không tìm thấy kỹ năng để xóa!", 404);

            return response()->json([
                'success' => true,
                'message' => 'Xóa kỹ năng thành công'
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