<?php
// app/Http/Controllers/JobController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\JobService;
use App\Http\Requests\CreateJobRequest;
use App\Http\Requests\UpdateJobRequest;
use Exception;

class JobController extends Controller
{
    protected $jobService;

    public function __construct(JobService $jobService)
    {
        $this->jobService = $jobService;
    }

    public function getAllJobs(Request $request)
    {
        try {
            $filters = [
                'page'      => (int) $request->query('page', 1),
                'limit'     => (int) $request->query('limit', 10),
                'categoryId' => $request->query('categoryId') ? (int) $request->query('categoryId') : null,
                'location'  => $request->query('location'),
                'minSalary' => $request->query('minSalary') ? (int) $request->query('minSalary') : null,
                'maxSalary' => $request->query('maxSalary') ? (int) $request->query('maxSalary') : null,
            ];
            $userId = $request->user()?->UserID;
            $data = $this->jobService->getAllJobs($filters, $userId);
            return response()->json(['success' => true, 'message' => 'Lấy tất cả job thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getJobDetail(Request $request, $id)
    {
        try {
            $job = $this->jobService->getJobDetail((int) $id);
            if (!$job) return response()->json(['success' => false, 'message' => 'Không tìm thấy công việc'], 404);

            $this->jobService->incrementJobViews((int) $id, $request->user()?->UserID, $request->ip());
            return response()->json(['success' => true, 'message' => 'Lấy chi tiết công việc thành công', 'data' => $job]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createJob(CreateJobRequest $request)
    {
        try {
            $employerId = $request->user()->UserID;
            $data = $request->validated();
            $jobId = $this->jobService->createJob($employerId, $data);
            return response()->json(['success' => true, 'message' => 'Tạo công việc thành công', 'data' => $jobId], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function closeJob(Request $request, $id)
    {
        try {
            $employerId = $request->user()->UserID;
            $this->jobService->closeJob($employerId, (int) $id);
            return response()->json(['success' => true, 'message' => 'Ẩn khỏi danh sách thành công']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function updateJob(UpdateJobRequest $request, $id)
    {
        try {
            $employerId = $request->user()->UserID;
            $this->jobService->updateJob($employerId, (int) $id, $request->validated());
            return response()->json(['success' => true, 'message' => 'Cập nhật công việc thành công', 'data' => $id]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function getJobOfMe(Request $request)
    {
        try {
            $employerId = $request->user()->UserID;
            $page   = (int) $request->query('page', 1);
            $limit  = (int) $request->query('limit', 10);
            $status = $request->query('status', 'All');
            $data = $this->jobService->getJobOfMe($employerId, $page, $limit, $status);
            return response()->json(['success' => true, 'message' => 'Lấy công việc của bạn thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getRecommendedJobs(Request $request)
    {
        try {
            $candidateId = $request->user()->UserID;
            $page  = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);
            $data = $this->jobService->getRecommendedJobs($candidateId, $page, $limit);
            return response()->json(['success' => true, 'message' => 'Lấy danh sách công việc được đề xuất thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getAllCategories()
    {
        try {
            $data = $this->jobService->getAllCategories();
            return response()->json(['success' => true, 'message' => 'Lấy danh sách category thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function searchJobByCategory(Request $request, $categoryId)
    {
        try {
            $page  = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);
            $data = $this->jobService->searchJobByCategory((int) $categoryId, $page, $limit);
            return response()->json(['success' => true, 'message' => 'Lấy công việc theo category thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function changeStatusJob(Request $request, $id)
    {
        try {
            $request->validate(['status' => 'required|in:Pending,Approved,Rejected']);
            $this->jobService->changeStatusJob((int) $id, $request->status);
            return response()->json(['success' => true, 'message' => 'Thay đổi trạng thái công việc thành công']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function get7DayStats()
    {
        try {
            $data = $this->jobService->get7DayStats();
            return response()->json(['success' => true, 'message' => 'Lấy thống kê 7 ngày thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getMonthlyStats()
    {
        try {
            $data = $this->jobService->getMonthlyStats();
            return response()->json(['success' => true, 'message' => 'Lấy thống kê tháng thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getJobForAdmin()
    {
        try {
            $data = $this->jobService->getJobForAdmin();
            return response()->json(['success' => true, 'message' => 'Lấy công việc cho admin thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getJobForAdminByStatus(Request $request)
    {
        try {
            $status = $request->query('status', 'All');
            $page   = (int) $request->query('page', 1);
            $limit  = (int) $request->query('limit', 10);
            $data = $this->jobService->getJobForAdminByStatus($page, $limit, $status);
            return response()->json(['success' => true, 'message' => 'Lấy công việc theo trạng thái thành công', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
