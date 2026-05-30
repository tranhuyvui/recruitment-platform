<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobApplicationRequest;
use App\Services\JobApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class JobApplicationController extends Controller
{
    protected JobApplicationService $jobApplicationService;

    public function __construct(JobApplicationService $jobApplicationService)
    {
        $this->jobApplicationService = $jobApplicationService;
    }

    public function getListApplicationByJobId(JobApplicationRequest $request, int $JobID): JsonResponse
    {
        try {
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);

            if ($page < 1) {
                $page = 1;
            }

            if ($limit < 1) {
                $limit = 10;
            }

            $cacheKey = "application:job:{$JobID}:page:{$page}:limit:{$limit}";

            
            // Nếu Redis đã chạy thì mở lại đoạn này
            $cachedData = Redis::get($cacheKey);

            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy danh sách các đơn ứng tuyển (cache)',
                    'data' => json_decode($cachedData, true)
                ], 200);
            }
            

            $data = $this->jobApplicationService->getApplicationByJobId($JobID, $page, $limit);

            /*
            */
            Redis::setex($cacheKey, 60 * 5, json_encode($data));

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách các đơn ứng tuyển thành công',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getApplicationDetail(JobApplicationRequest $request, int $ApplicationID): JsonResponse
    {
        try {
            $userID = $request->user()->UserID;
            $role = $request->user()->Role;

            $cacheKey = "applications:detail:{$ApplicationID}";
            $cached = Redis::get($cacheKey);

            if ($cached) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy đơn ứng tuyển thành công (redis)',
                    'data' => json_decode($cached, true)
                ], 200);
            }

            $data = $this->jobApplicationService->getApplicationDetail($ApplicationID);

            Redis::setex($cacheKey, 60 * 5, json_encode($data));

            if ($role === 'Employer') {
                $this->jobApplicationService->updateApplicationStatus(
                    $ApplicationID,
                    $userID,
                    'Reviewed'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy đơn ứng tuyển thành công',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getSubmittedApplications(Request $request): JsonResponse
    {
        try {
            $candidateID = $request->user()->UserID;
    
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 6);
    
            if ($page < 1) {
                $page = 1;
            }
    
            if ($limit < 1) {
                $limit = 6;
            }
    
            $cacheKey = "application:submitted:candidate:{$candidateID}:page:{$page}:limit:{$limit}";
    
            
            $cachedData = Redis::get($cacheKey);
    
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy danh sách đã nộp thành công',
                    'data' => json_decode($cachedData, true)
                ], 200);
            }
            
    
            $data = $this->jobApplicationService->getSubmittedApplications(
                $candidateID,
                $page,
                $limit
            );
    
            /*
            Redis::setex($cacheKey, 60 * 5, json_encode($data));
            */
    
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách đã nộp thành công',
                'data' => $data
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getChartStatsController(Request $request): JsonResponse
    {
        try {
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $userID = $request->user()->UserID;
            
            $type = $request->query('type', 'week');
    
            $data = $this->jobApplicationService->getChartStats($userID, $type);
    
            return response()->json([
                'success' => true,
                'message' => 'Lấy thống kê ứng tuyển thành công',
                'data' => $data
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    
    public function applyJob(JobApplicationRequest $request): JsonResponse
    {
        try {
            $jobID = (int) $request->input('JobID');
            $resumeID = (int) $request->input('ResumeID');
    
            $candidateID = $request->user()->UserID;
    
            $applicationID = $this->jobApplicationService->createJobApplication(
                $jobID,
                $candidateID,
                $resumeID
            );
    
            try {
                $this->jobApplicationService->analyzeApplicationWithAI(
                    $applicationID,
                    $jobID,
                    $resumeID
                );
            } catch (\Throwable $aiError) {
                // Bỏ qua lỗi AI để người dùng vẫn ứng tuyển thành công
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Ứng tuyển thành công',
                'data' => [
                    'ApplicationID' => $applicationID
                ]
            ], 201);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function updateApplicationStatus(JobApplicationRequest $request, int $ApplicationID): JsonResponse
    {
        try {
            $status = $request->input('Status');
    
            $userID = $request->user()->UserID;
            $role = $request->user()->Role;
    
            if ($role === 'Candidate') {
                $this->jobApplicationService->updateApplicationStatusCandidate(
                    $ApplicationID,
                    $userID,
                    $status
                );
            } else {
                $this->jobApplicationService->updateApplicationStatus(
                    $ApplicationID,
                    $userID,
                    $status
                );
            }
    
            
            // Nếu Redis chạy thì mở lại
            // $jobID = $this->jobApplicationService->getJobIdByApplicationId($ApplicationID);
    
            $keys = Redis::connection()->keys('application:job:*');

            if (!empty($keys)) {
                Redis::connection()->del($keys);
            }
    
            Redis::del("applications:detail:{$ApplicationID}");
    
            $keySubmitteds = Redis::connection()->keys("application:submitted:candidate:{$userID}:*");
            if (!empty($keySubmitteds)) {
                Redis::del($keySubmitteds);
            }
            
    
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công'
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
            'message' => $message
        ], $statusCode);
    }
}