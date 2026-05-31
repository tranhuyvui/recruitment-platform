<?php

namespace App\Http\Controllers;

use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Services\EmployerService;
use App\Http\Requests\CompanyRequest;

use Exception;

class CompanyController extends Controller
{
    protected CompanyService $companyService;
    protected EmployerService $employerService;


    public function __construct(CompanyService $companyService, EmployerService $employerService)
    {
        $this->companyService = $companyService;
        $this->employerService = $employerService;
        
    }

    public function getAllCompany(Request $request): JsonResponse
    {
        try {

            // Nếu có user đăng nhập thì lấy role, không có thì mặc định Candidate
            $role = $request->user()->Role ?? 'Candidate';

            $cacheKey = "company:role:{$role}:all";

            // Lấy dữ liệu từ Redis cache
            $cachedData = Redis::get($cacheKey);

            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy danh sách công ty thành công (từ cache)',
                    'data' => json_decode($cachedData, true)
                ], 200);
            }

            // Gọi Service xử lý
            $companies = $this->companyService->getAllCompany($role);

            // Lưu cache 300 giây
            Redis::setex($cacheKey, 300, json_encode($companies));

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách công ty thành công',
                'data' => $companies
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getCompanyDetail(Request $request, int $companyID): JsonResponse
    {
        try {
            $role = $request->user()?->Role ?? 'Candidate';

            $cacheKey = "company:role:{$role}:{$companyID}";

            if ($role === 'Employer') {
                $employerID = $request->user()?->UserID;

                if (!$employerID) {
                    throw new \Exception('Không xác định được tài khoản Employer', 401);
                }

                $this->companyService->checkEmployer($employerID);
            }

            $cachedData = Redis::get($cacheKey);

            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy thông tin công ty thành công (từ cache)',
                    'data' => json_decode($cachedData, true)
                ], 200);
            }

            $company = $this->companyService->getCompanyDetail($role, $companyID);

            Redis::setex($cacheKey, 300, json_encode($company));

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin công ty thành công',
                'data' => $company
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getCompanyOfMe(Request $request): JsonResponse
    {
        try {
            $userID = $request->user()?->UserID;
            if($request->user()?->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền xem công ty của bạn', 403);
            }
            if (!$userID) {
                throw new \Exception('Không xác định được người dùng', 401);
            }

            $data = $this->companyService->getCompanyOfMe($userID);

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin công ty của bạn thành công',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getCompanyDetailOfMe(Request $request): JsonResponse
    {
        try {
            // Kiểm tra employer có thuộc công ty không, có được duyệt chưa
            $employerID = $request->user()?->UserID;
            if (!$employerID) {
                throw new \Exception('Không xác định được tài khoản Employer', 401);
            }
            $this->companyService->checkEmployer($employerID);
            // Lấy CompanyID của employer
            $companyID = $this->companyService->getCompanyIdOfMe($employerID);

            if ($companyID === null) {
                throw new Exception('Bạn không thuộc công ty nào', 403);
            }

            // Vì chưa có user/JWT nên tạm role là Employer
            $role = $request->user()?->Role ?? 'Candidate';


            $cacheKey = "company-ofme:role:{$role}:{$companyID}";

            // Nếu Redis chưa chạy thì tạm comment đoạn Redis này
            
            $cachedData = Redis::get($cacheKey);

            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy thông tin công ty của bạn thành công (từ cache)',
                    'data' => json_decode($cachedData, true)
                ], 200);
            }
            

            $company = $this->companyService->getCompanyDetail($role, $companyID);

            Redis::setex($cacheKey, 300, json_encode($company));

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin công ty của bạn thành công',
                'data' => $company
            ], 200);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getAllCompanyForAdmin(Request $request): JsonResponse
    {
        try {
            if($request->user()?->Role !== 'Admin') {
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
    
            $data = $this->companyService->getAllCompanyForAdmin($page, $limit);
    
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách công ty cho admin thành công',
                'data' => $data
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function getCompanyByIdForAdmin(int $companyID): JsonResponse
    {
        try {
            if(request()->user()?->Role !== 'Admin') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            $cacheKey = "company:admin:{$companyID}";

            $cachedData = Redis::get($cacheKey);
    
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Lấy thông tin công ty cho admin thành công (từ cache)',
                    'data' => json_decode($cachedData, true)
                ], 200);
            }
            
    
            $company = $this->companyService->getCompanyDetailForAdmin($companyID);
    
            Redis::setex($cacheKey, 300, json_encode($company));
    
            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin công ty cho admin thành công',
                'data' => $company
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateCompanyStatus(int $companyID, CompanyRequest $request): JsonResponse
    {
        try {
            if(request()->user()?->Role !== 'Admin') {
                throw new \Exception('Bạn không có quyền truy cập', 403);
            }
            // 1. Kiểm tra xem request có chứa key 'status' không
            if (!$request->has('status')) {
                throw new \Exception('Trạng thái công ty không được để trống', 400);
            }
    
            // 2. Ép kiểu an toàn về boolean (nhận cả chuỗi 'true'/'false' hoặc 1/0)
            $status = $request->boolean('status');
    
            // 3. Truyền biến boolean vào Service
            $this->companyService->updateCompanyStatusForAdmin($companyID, $status);
    
            $cacheKey = "company:admin:{$companyID}";
            Redis::del($cacheKey);
    
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái công ty cho admin thành công'
            ], 200);
    
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
    public function createCompany(CompanyRequest $request): JsonResponse
    {
        $uploadedAssets = [];

        try {
            DB::beginTransaction();
            $userID = $request->user()->UserID;
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền tạo công ty', 403);
            }

            $companyData = [
                'CompanyName' => $request->input('CompanyName'),
                'CompanyDescription' => $request->input('CompanyDescription'),
                'TaxCode' => $request->input('TaxCode'),
                'Industry' => $request->input('Industry'),
                'Website' => $request->input('Website'),
                'ContactEmail' => $request->input('ContactEmail'),
                'City' => $request->input('City'),
                'CreatedBy' => $userID,
                'BusinessLicenseUrl' => '',
            ];

            $uploaded = $this->companyService->handleCompanyUploads($request);

            $uploadedAssets = $uploaded['publicIds'];

            $companyData = array_merge($companyData, $uploaded['data']);

            if (empty($companyData['BusinessLicenseUrl'])) {
                throw new \Exception('Giấy phép kinh doanh không được để trống', 400);
            }

            $companyID = $this->companyService->createCompany($companyData);

            $employerID = $this->employerService->createEmployer([
                'EmployerID' => $userID,
                'CompanyID' => $companyID,
                'Position' => $request->input('Position'),
                'ApprovalStatus' => 'Approved',
            ]);

            DB::commit();

            Redis::del('company:role:Candidate:all');

            return response()->json([
                'success' => true,
                'message' => 'Tạo Công ty thành công',
                'data' => [
                    'CompanyID' => $companyID,
                    'EmployerID' => $employerID,
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            if (!empty($uploadedAssets)) {
                $this->companyService->cleanupCloudinary($uploadedAssets);
            }

            return $this->errorResponse($e);
        }
    }
    public function updateCompany(CompanyRequest $request, int $companyID): JsonResponse
    {
        $uploadedAssets = [];
    
        try {
            if($request->user()->Role !== 'Employer') {
                throw new \Exception('Bạn không có quyền cập nhật công ty', 403);
            }
            $employerID = $request->user()->UserID;
            $this->companyService->checkEmployer($employerID);
            $companyData = $request->only([
                'CompanyName',
                'CompanyDescription',
                'TaxCode',
                'Industry',
                'Website',
                'LogoUrl',
                'ContactEmail',
                'City',
                'Position',
                'BusinessLicenseUrl',
            ]);
    
            if ($request->hasFile('LogoUrl') || $request->hasFile('BusinessLicenseUrl')) {
                $uploaded = $this->companyService->handleCompanyUploads($request);
    
                $uploadedAssets = $uploaded['publicIds'];
    
                $companyData = array_merge($companyData, $uploaded['data']);
            }
    
            $this->companyService->updateCompany($companyID, $companyData);
            $role = 'Employer';
    
            Redis::del("company:role:{$role}:{$companyID}");
            Redis::del("company:role:{$role}:all");
            Redis::del("company-ofme:role:{$role}:{$companyID}");
        
    
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thông tin công ty thành công',
            ], 200);
    
        } catch (\Throwable $e) {
            if (!empty($uploadedAssets)) {
                $this->companyService->cleanupCloudinary($uploadedAssets);
            }
    
            return $this->errorResponse($e);
        }
    }
    
    public function requestCompany(CompanyRequest $request, int $companyID): JsonResponse
    {
        try {
            $position = $request->input('Position');
            $employerID = $request->user()->UserID;
    
            if (!$position) {
                throw new \Exception('Vị trí không được để trống', 400);
            }
    
            $companyExist = $this->companyService->checkCompanyId($companyID);
    
            if (!$companyExist) {
                throw new \Exception('Công ty không tồn tại', 404);
            }
    
            $createdEmployerID = $this->employerService->createEmployer([
                'EmployerID' => $employerID,
                'CompanyID' => $companyID,
                'Position' => $position,
                'ApprovalStatus' => 'Pending',
            ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Gửi yêu cầu vào công ty thành công',
                'data' => $createdEmployerID
            ], 201);
    
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
