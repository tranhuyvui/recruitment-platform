<?php

namespace App\Http\Controllers;

use App\Services\ResumeService;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\ResumeRequest;
use App\Services\SearchAiService;

class ResumeController extends Controller
{
    protected ResumeService $resumeService;
    protected CloudinaryService $cloudinaryService; 
    protected SearchAiService $searchAiService;

    public function __construct(
        ResumeService $resumeService,
        CloudinaryService $cloudinaryService,
        SearchAiService $searchAiService
    ) {
        $this->resumeService = $resumeService;
        $this->cloudinaryService = $cloudinaryService;
        $this->searchAiService = $searchAiService;

    }

    public function createManualResume(ResumeRequest $request): JsonResponse
    {
        try {
            $candidateId = $request->user()->UserID;

            if($request->user()->Role !== 'Candidate') {
                throw new \Exception('Chỉ ứng viên mới được tạo CV', 403);
            }
            $resumeData = $request->all();  

            if ($request->hasFile('AvatarUrl')) {
                $uploaded = $this->cloudinaryService->uploadToCloudinary(
                    'Resumes',
                    $request->file('AvatarUrl')
                );

                $resumeData['AvatarUrl'] = $uploaded['url'];
            } elseif ($request->input('ExistingAvatarUrl')) {
                $resumeData['AvatarUrl'] = $request->input('ExistingAvatarUrl');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng cung cấp ảnh đại diện cho CV!'
                ], 400);
            }

            $candidateProfile = [
                'CandidateID' => $candidateId,
                'FullName' => $request->input('FullName'),
                'Phone' => $request->input('Phone'),
                'DateOfBirth' => $request->input('DateOfBirth'),
                'Address' => $request->input('Address'),
                'AvatarUrl' => $resumeData['AvatarUrl'],
            ];
            

            $result = $this->resumeService->buildManualResume(
                $candidateId,
                $resumeData,
                $candidateProfile
            );
            // try {
            //     $resumeDetail = $this->resumeService->getResumeDetail(
            //         $result['resumeId'],
            //         $candidateId
            //     );
            
            //     if ($resumeDetail) {
            //         $this->searchAiService->recommendJobsByAI($resumeDetail, $candidateId);
            //     }
            // } catch (\Throwable $recommendError) {
            //     // Bỏ qua lỗi gợi ý job để không làm lỗi tạo CV
            // }
            
            Redis::connection()->del("resumes:list:{$candidateId}");
            Redis::connection()->del('all_skills');

            $keys = Redis::connection()->keys("jobs_list:u{$candidateId}:*");

            foreach ($keys as $key) {
                Redis::connection()->del($key);
            }
            

            return response()->json([
                'success' => true,
                'message' => 'Tạo CV thành công',
                'data' => $result
            ], 201);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getListResumes(Request $request): JsonResponse
    {
        try {
            $candidateId = $request->user()->UserID;
    
            $resumes = $this->resumeService->getListResume($candidateId);
    
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách CV thành công!',
                'data' => $resumes
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getMyResumes(Request $request): JsonResponse
    {
        try {
            $candidateId = $request->user()->UserID;
    
            
            $cachedResumes = Redis::connection()->get("resumes:list:{$candidateId}");
    
            if ($cachedResumes) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy danh sách CV từ Redis cực mượt!',
                    'data' => json_decode($cachedResumes, true)
                ], 200);
            }
            
    
            $resumes = $this->resumeService->getCandidateResumes($candidateId);
    
            
            // Nếu Redis chạy thì mở lại
            if (!empty($resumes)) {
                Redis::connection()->setex(
                    "resumes:list:{$candidateId}",
                    3600,
                    json_encode($resumes)
                );
            }
            
    
            return response()->json([
                'success' => true,
                'data' => $resumes
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getResumeDetail(Request $request, string $resumeId): JsonResponse
    {
        try {
            if (!ctype_digit($resumeId) || (int) $resumeId < 1) {
                throw new \Exception('resumeId phải là số nguyên dương', 400);
            }
    
            $candidateId = $request->user()->UserID;
            $resumeID = (int) $resumeId;
    
            $detail = $this->resumeService->getResumeDetail($resumeID, $candidateId);
    
            if (!$detail) {
                throw new \Exception('Không tìm thấy CV này hoặc bạn không có quyền xem!', 404);
            }
    
            return response()->json([
                'success' => true,
                'data' => $detail
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getResumeDetailByEmployer(Request $request, string $resumeId): JsonResponse
    {
        try {
            if (!ctype_digit($resumeId) || (int) $resumeId < 1) {
                throw new \Exception('resumeId phải là số nguyên dương', 400);
            }
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Chỉ nhà tuyển dụng mới được xem CV này', 403);
            }
            $resumeID = (int) $resumeId;
    
            $detail = $this->resumeService->getResumeForEmployer($resumeID);
    
            if (!$detail) {
                throw new \Exception('Không tìm thấy CV này hoặc ứng viên đã xóa!', 404);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'HR lấy chi tiết CV thành công!',
                'data' => $detail
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function deleteResume(Request $request, string $resumeId): JsonResponse
    {
        try {
            if (!ctype_digit($resumeId) || (int) $resumeId < 1) {
                throw new \Exception('resumeId phải là số nguyên dương', 400);
            }
    
            $candidateId = $request->user()->UserID;
            $resumeID = (int) $resumeId;
    
            $isDeleted = $this->resumeService->deleteResume($resumeID, $candidateId);
    
            if (!$isDeleted) {
                throw new \Exception('Không tìm thấy CV để xóa hoặc bạn không có quyền!', 404);
            }
    
            Redis::connection()->del("resumes:list:{$candidateId}");
    
            return response()->json([
                'success' => true,
                'message' => 'Đã xóa CV thành công!'
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function updateManualResume(ResumeRequest $request, string $resumeId): JsonResponse
    {
        try {
            if (!ctype_digit($resumeId) || (int) $resumeId < 1) {
                throw new \Exception('resumeId phải là số nguyên dương', 400);
            }
    
            $candidateId = $request->user()->UserID;
            $resumeID = (int) $resumeId;
    
            $resumeData = $request->all();
    
            $result = $this->resumeService->updateManualResume(
                $candidateId,
                $resumeID,
                $resumeData
            );
            
    
            if (!$result) {
                throw new \Exception('Không tìm thấy CV này hoặc bạn không có quyền chỉnh sửa!', 404);
            }
            // try {
            //     $resumeDetail = $this->resumeService->getResumeDetail(
            //         $result['resumeId'],
            //         $candidateId
            //     );
            
            //     if ($resumeDetail) {
            //         $this->searchAiService->recommendJobsByAI($resumeDetail, $candidateId);
            //     }
            // } catch (\Throwable $recommendError) {
            //     // Bỏ qua lỗi gợi ý job để không làm lỗi tạo CV
            // }
            Redis::connection()->del("resumes:list:{$candidateId}");
            Redis::connection()->del('all_skills');
    
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật CV thành công rực rỡ!',
                'data' => $result
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function generateSummaryWithAI(ResumeRequest $request): JsonResponse
    {
        try {
            $resumeData = $request->all();
    
            $generatedText = $this->resumeService->generateResumeSummary($resumeData);
    
            if (!$generatedText) {
                throw new \Exception('Hệ thống AI đang quá tải, bạn chịu khó tự gõ tóm tắt nhé!', 500);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'AI đã viết xong tóm tắt cực mượt!',
                'data' => $generatedText
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    private function errorResponse(\Throwable $e): JsonResponse
    {
        $statusCode = $e->getCode();

        if (!is_numeric($statusCode) || $statusCode < 100 || $statusCode > 599) {
            $statusCode = 500;
        }

        $statusCode = (int) $statusCode;

        $message = $statusCode === 500
            ? 'Internal Server Error'
            : $e->getMessage();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], $statusCode);
    }
}
