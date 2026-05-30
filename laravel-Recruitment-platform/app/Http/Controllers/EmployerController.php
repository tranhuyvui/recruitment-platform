<?php

namespace App\Http\Controllers;

use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\EmployerRequest;
use Illuminate\Http\Request;

class EmployerController extends Controller
{
    protected EmployerService $employerService;

    public function __construct(EmployerService $employerService)
    {
        $this->employerService = $employerService;
    }
    public function updateStatusEmployer(EmployerRequest $request, int $EmployerID): JsonResponse

    {
        try {
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $userID = $request->user()->UserID;

            $approvalStatus = $request->input('ApprovalStatus');

            if (!$approvalStatus) {
                throw new \Exception('ApprovalStatus không được để trống', 400);
            }

            $this->employerService->updateStatusEmployer(
                $EmployerID,
                $userID,
                $approvalStatus
            );

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái nhân viên thành công'
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getPendingEmployers(Request $request): JsonResponse
    {
        try {
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $userID = $request->user()->UserID;

            $status = $request->query('status', 'all');

            $data = $this->employerService->getPendingEmployers($userID, $status);

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách yêu cầu nhân viên thành công',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getDashboardStats(Request $request): JsonResponse
    {
        try {
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $userID = $request->user()->UserID;

            $stats = $this->employerService->getDashboardStats($userID);

            return response()->json([
                'success' => true,
                'message' => 'Lấy thống kê dashboard thành công',
                'data' => $stats
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getTopEmployers(Request $request): JsonResponse
    {
        try {
            if($request->user()->Role !== 'Admin') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $employers = $this->employerService->getTopEmployers();

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách nhà tuyển dụng hàng đầu thành công',
                'data' => $employers
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getLogoTopEmployers(): JsonResponse
    {
        try {
            $logos = $this->employerService->getLogoTopEmployers();

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách logo nhà tuyển dụng hàng đầu thành công',
                'data' => $logos
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getAllEmployers(Request $request): JsonResponse
    {
        try {
            if($request->user()->Role !== 'Admin') {
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

            $employers = $this->employerService->getAllEmployers($page, $limit);

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách nhà tuyển dụng thành công',
                'data' => $employers
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