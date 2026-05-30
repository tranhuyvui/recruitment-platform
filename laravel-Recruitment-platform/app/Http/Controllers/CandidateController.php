<?php

namespace App\Http\Controllers;

use App\Services\CandidateService;
use App\Services\CloudinaryService;

use Illuminate\Support\Facades\DB; 
use App\Http\Requests\CandidateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Exception;

class CandidateController extends Controller
{
    protected CandidateService $candidateService;
    protected CloudinaryService $cloudinaryService;

    public function __construct(CandidateService $candidateService, CloudinaryService $cloudinaryService)
    {
        $this->candidateService = $candidateService;
        $this->cloudinaryService = $cloudinaryService;
    }

    public function upsertProfile(CandidateRequest $request)
    {
        try {
            $userId = $request->user()->UserID;
            $data = $request->validated();
            $avatarUrl = $data['AvatarUrl'] ?? null;

            if ($request->hasFile('AvatarUrl')) {
                $uploaded = $this->cloudinaryService->uploadToCloudinary('Candidates', $request->file('AvatarUrl'));
                $avatarUrl = $uploaded['url'];
                Redis::del("profile:u{$userId}");
            }

            $profileData = array_merge($data, [
                'CandidateID' => $userId,
                'AvatarUrl' => $avatarUrl
            ]);

            $this->candidateService->upsertCandidateProfile($profileData);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thông tin cơ bản ứng viên thành công!',
                'data' => $profileData
            ], 200);

        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateMasterProfileDetail(CandidateRequest $request)
    {
        try {
            $userId = $request->user()->UserID;
            $detailData = $request->validated();

            $updatedDetail = $this->candidateService->upsertCandidateDetailMongo($userId, $detailData);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật Hồ sơ chi tiết thành công!',
                'data' => $updatedDetail
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getProfile(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $profile = $this->candidateService->getCandidateProfile($userId);

            if (!$profile) {
                throw new Exception("Không tìm thấy thông tin hồ sơ ứng viên!", 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin hồ sơ thành công',
                'data' => $profile
            ], 200);
        } catch (Exception $e) {
            dd("Lỗi nè Đăng ơi: ", $e->getMessage(), "Ở dòng số: ", $e->getLine());
            return $this->errorResponse($e);
        }
    }

    public function getCandidateInfo(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $profile = $this->candidateService->getCandidateInfo($userId);

            if (!$profile) {
                throw new Exception("Không tìm thấy thông tin hồ sơ ứng viên!", 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin hồ sơ thành công',
                'data' => $profile
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getSkills(Request $request)
    {
        try {
            $userId = $request->user()->UserID;
            $skills = $this->candidateService->getCandidateSkills($userId);

            if (!$skills) {
                throw new Exception("Không tìm thấy kỹ năng hồ sơ ứng viên!", 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin kỹ năng hồ sơ thành công',
                'data' => $skills
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function analyzeSkillsText(CandidateRequest $request)
    {
        try {
            $rawText = $request->input('rawText');
            $finalSkills = $this->candidateService->analyzeTextWithAI($rawText);

            return response()->json([
                'success' => true,
                'message' => 'Phân tích kỹ năng thành công!',
                'data' => $finalSkills
            ], 200);
        } catch (Exception $e) {
            if ($e->getMessage() === "AI_PARSE_ERROR") {
                return response()->json([
                    'success' => false,
                    'message' => 'Hệ thống AI đang quá tải không thể phân tích, vui lòng thử lại sau!'
                ], 500);
            }
            return $this->errorResponse($e);
        }
    }

    public function saveAnalyzedSkills(CandidateRequest $request)
    {
        try {
            $userId = $request->user()->UserID;
            $skills = $request->input('skills');

            $this->candidateService->saveSkillsTransaction($userId, $skills);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật hồ sơ kỹ năng thành công!'
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getCandidatesForEmployer(Request $request)
    {
        try {
            if ($request->user()->Role !== 'Employer') {
                throw new Exception("Lỗi phân quyền: Chỉ Nhà tuyển dụng mới được quyền xem danh sách này!", 403);
            }

            $candidates = $this->candidateService->getCandidatesListForEmployer();
            
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách ứng viên thành công!',
                'data' => $candidates
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getCandidateDetailForEmployer(CandidateRequest $request)
    {
        try {
            if ($request->user()->Role !== 'Employer') {
                throw new Exception("Lỗi phân quyền: Chỉ Nhà tuyển dụng mới được xem chi tiết ứng viên!", 403);
            }

            $candidateId = (int) $request->input('id'); 

            $profile = $this->candidateService->getCandidateProfile($candidateId);
            if (!$profile) {
                throw new Exception("Không tìm thấy thông tin ứng viên này!", 404);
            }

            $skills = $this->candidateService->getCandidateSkills($candidateId);
            $resumes = DB::table('Resumes')->where('CandidateID', $candidateId)->get();

            return response()->json([
                'success' => true,
                'message' => 'Lấy full thông tin ứng viên thành công!',
                'data' => [
                    'profile' => $profile,
                    'skills' => $skills ?? [],
                    'resumes' => $resumes ?? []
                ]
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getAllCandidates(Request $request)
    {
        try {
            if ($request->user()->Role !== 'Admin') {
                throw new Exception("Lỗi phân quyền: Chỉ Admin mới được quyền truy cập toàn bộ dữ liệu!", 403);
            }

            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);
            
            $candidates = $this->candidateService->getAllCandidates($page, $limit);
            
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách ứng viên thành công!',
                'data' => $candidates
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