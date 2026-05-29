<?php

namespace App\Http\Controllers;

use App\Services\SavedJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class SavedJobController extends Controller
{
    protected SavedJobService $savedJobService;

    public function __construct(SavedJobService $savedJobService)
    {
        $this->savedJobService = $savedJobService;
    }

    public function savedJob(Request $request, string $jobId): JsonResponse
    {
        try {
            if (!ctype_digit($jobId) || (int) $jobId < 1) {
                throw new \Exception('jobId phải là số nguyên dương', 400);
            }

            $userID = $request->user()->UserID;
            $jobID = (int) $jobId;

            $isSaved = $this->savedJobService->isSavedJob($userID, $jobID);

            if ($isSaved) {
                throw new \Exception('Bạn đã lưu tin này rồi', 409);
            }

            $this->savedJobService->savedJob($userID, $jobID);

            
            $cacheKeys = Redis::connection()->keys("saved_jobs:u{$userID}:*");

            foreach ($cacheKeys as $key) {
                Redis::connection()->del($key);
            }
            

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu tin thành công'
            ], 201);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function removeSavedJob(Request $request, string $jobId): JsonResponse
    {
        try {
            if (!ctype_digit($jobId) || (int) $jobId < 1) {
                throw new \Exception('jobId phải là số nguyên dương', 400);
            }

            $userID = $request->user()->UserID;
            $jobID = (int) $jobId;

            $isSaved = $this->savedJobService->isSavedJob($userID, $jobID);

            if (!$isSaved) {
                throw new \Exception('Bạn chưa lưu tin này', 400);
            }

            $this->savedJobService->removeSavedJob($userID, $jobID);

            
            $cacheKeys = Redis::connection()->keys("saved_jobs:u{$userID}:*");

            foreach ($cacheKeys as $key) {
                Redis::connection()->del($key);
            }
            

            return response()->json([
                'success' => true,
                'message' => 'Đã bỏ lưu tin thành công'
            ], 201);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getMySavedJobs(Request $request): JsonResponse
    {
        try {
            $userID = $request->user()->UserID;

            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);

            if ($page < 1) {
                $page = 1;
            }

            if ($limit < 1) {
                $limit = 10;
            }

            $cacheKey = "saved_jobs:u{$userID}:p{$page}:l{$limit}";

            
            $cachedJobs = Redis::get($cacheKey);

            if ($cachedJobs) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy dữ liệu công việc đã lưu thành công(cache)',
                    'data' => json_decode($cachedJobs, true)
                ], 200);
            }
            

            $jobs = $this->savedJobService->getSavedJobs($userID, $page, $limit);

            
            Redis::setex($cacheKey, 300, json_encode($jobs));
            

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu công việc đã lưu thành công',
                'data' => $jobs
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function isSavedJob(Request $request, string $jobId): JsonResponse
    {
        try {
            if (!ctype_digit($jobId) || (int) $jobId < 1) {
                throw new \Exception('jobId phải là số nguyên dương', 400);
            }

            $userID = $request->user()->UserID;
            $jobID = (int) $jobId;

            $isSaved = $this->savedJobService->isSavedJob($userID, $jobID);

            return response()->json([
                'success' => true,
                'message' => 'Kiểm tra trạng thái lưu tin thành công',
                'data' => $isSaved
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