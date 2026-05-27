<?php

namespace App\Services;

use App\Models\CompanyModel;
use App\Models\EmployerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    protected CloudinaryService $cloudinaryService;
    protected EmployerService $employerService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    
    public function getAllCompany(string $role): array
    {
        if ($role === 'Admin') {
            return CompanyModel::query()
                ->get()
                ->toArray();
        }

        return CompanyModel::query()
            ->select([
                'CompanyID',
                'CompanyName',
                'Industry',
                'City',
                'LogoUrl'
            ])
            ->where('Status', 'Approved')
            ->get()
            ->toArray();
    }

    public function checkEmployer(int $employerID): void
    {
        $employer = EmployerModel::query()
            ->where('EmployerID', $employerID)
            ->first();

        if (!$employer) {
            throw new \Exception('Bạn không thuộc công ty nào', 403);
        }

        if ($employer->ApprovalStatus === 'Pending') {
            throw new \Exception('Yêu cầu của bạn đang chờ phê duyệt', 403);
        }

        if ($employer->ApprovalStatus === 'Rejected') {
            throw new \Exception('Yêu cầu của bạn đã bị từ chối', 403);
        }
    }
    
    public function checkCompanyId(int $companyID): bool
    {
        return CompanyModel::query()
            ->where('CompanyID', $companyID)
            ->exists();
    }

    public function getCompanyDetail(string $role, int $companyID): array
    {
        $company = CompanyModel::query()
            ->from('Companies as c')
            ->join('Employers as e', 'c.CompanyID', '=', 'e.CompanyID')
            ->select('c.*', 'e.Position')
            ->where('c.CompanyID', $companyID)
            ->first();

        if (!$company) {
            throw new \Exception('Công ty không tồn tại hoặc bạn không có quyền xem', 404);
        }

        return $company->toArray();
    }
    public function getCompanyOfMe(int $userID): ?array
    {
        $company = EmployerModel::query()
            ->from('Employers as e')
            ->join('Companies as c', 'c.CompanyID', '=', 'e.CompanyID')
            ->select([
                'c.CompanyID',
                'c.CompanyName',
                'c.LogoUrl'
            ])
            ->where('e.EmployerID', $userID)
            ->where('e.ApprovalStatus', 'Approved')
            ->first();

        return $company ? $company->toArray() : null;
    }
    public function getCompanyIdOfMe(int $employerID): ?int
    {
        $employer = EmployerModel::query()
            ->select('CompanyID')
            ->where('EmployerID', $employerID)
            ->first();

        if (!$employer) {
            return null;
        }

        return (int) $employer->CompanyID;
    }
    public function getAllCompanyForAdmin(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $response = [];

        if ($page === 1) {
            $total = CompanyModel::query()->count();
            $totalPages = (int) ceil($total / $limit);

            $response['total'] = $total;
            $response['totalPages'] = $totalPages;
        }

        $items = CompanyModel::query()
            ->select([
                'CompanyID',
                'CompanyName',
                'Industry',
                'City',
                'LogoUrl',
                'TaxCode',
                'Status'
            ])
            ->orderByDesc('CreatedAt')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        $response['items'] = $items;

        return $response;
    }
    public function getCompanyDetailForAdmin(int $companyID): array
    {
        $company = CompanyModel::query()
            ->select([
                'CompanyID',
                'CompanyName',
                'CompanyDescription',
                'Industry',
                'Website',
                'LogoUrl',
                'TaxCode',
                'BusinessLicenseUrl',
                'ContactEmail',
                'City',
                'Status'
            ])
            ->where('CompanyID', $companyID)
            ->first();
    
        if (!$company) {
            throw new \Exception('Công ty không tồn tại hoặc bạn không có quyền xem', 404);
        }
    
        return $company->toArray();
    }
    public function updateCompanyStatusForAdmin(int $companyID, string $status): bool
    {
        $affectedRows = CompanyModel::query()
            ->where('CompanyID', $companyID)
            ->update([
                'Status' => $status
            ]);
    
        if ($affectedRows === 0) {
            throw new \Exception('Công ty không tồn tại hoặc đã bị xóa', 404);
        }
    
        return true;
    }
    
    public function handleCompanyUploads(Request $request): array
    {
        $data = [];
        $publicIds = [];

        if ($request->hasFile('LogoUrl')) {
            $uploaded = $this->cloudinaryService->uploadToCloudinary(
                'Company',
                $request->file('LogoUrl')
            );

            $data['LogoUrl'] = $uploaded['url'];
            $publicIds[] = $uploaded['publicId'];
        }

        if ($request->hasFile('BusinessLicenseUrl')) {
            $uploaded = $this->cloudinaryService->uploadToCloudinary(
                'Company',
                $request->file('BusinessLicenseUrl')
            );

            $data['BusinessLicenseUrl'] = $uploaded['url'];
            $publicIds[] = $uploaded['publicId'];
        }

        return [
            'data' => $data,
            'publicIds' => $publicIds,
        ];
    }

    public function cleanupCloudinary(array $publicIds): void
    {
        $this->cloudinaryService->cleanupCloudinary($publicIds);
    }

    public function createCompany(array $company): int
    {
        $userAlreadyHasCompany = $this->checkUserCreatedCompany($company['CreatedBy']);

        if ($userAlreadyHasCompany) {
            throw new \Exception('Bạn đã tạo công ty rồi', 409);
        }

        $isExistTaxCode = $this->checkTaxCodeCompany($company['TaxCode']);

        if ($isExistTaxCode) {
            throw new \Exception('Mã số thuế đã tồn tại', 409);
        }

        $companyModel = CompanyModel::query()->create([
            'CompanyName' => $company['CompanyName'],
            'CompanyDescription' => $company['CompanyDescription'] ?? null,
            'Industry' => $company['Industry'],
            'Website' => $company['Website'] ?? null,
            'LogoUrl' => $company['LogoUrl'] ?? null,
            'ContactEmail' => $company['ContactEmail'] ?? null,
            'City' => $company['City'] ?? null,
            'TaxCode' => $company['TaxCode'],
            'CreatedBy' => $company['CreatedBy'],
            'BusinessLicenseUrl' => $company['BusinessLicenseUrl'],
        ]);

        return (int) $companyModel->CompanyID;
    }

    public function checkUserCreatedCompany(int $userID): bool
    {
        return CompanyModel::query()
            ->where('CreatedBy', $userID)
            ->exists();
    }

    public function checkTaxCodeCompany(string $taxCode): bool
    {
        return CompanyModel::query()
            ->where('TaxCode', $taxCode)
            ->exists();
    }
    
    public function updateCompany(int $companyID, array $companyData): bool
    {
        return DB::transaction(function () use ($companyID, $companyData) {
            $updateData = [];
    
            $allowedFields = [
                'CompanyName',
                'CompanyDescription',
                'TaxCode',
                'Industry',
                'Website',
                'LogoUrl',
                'ContactEmail',
                'City',
                'BusinessLicenseUrl',
            ];
    
            foreach ($allowedFields as $field) {
                if (
                    array_key_exists($field, $companyData) &&
                    $companyData[$field] !== null &&
                    $companyData[$field] !== ''
                ) {
                    $updateData[$field] = $companyData[$field];
                }
            }
    
            if (empty($updateData) && empty($companyData['Position'])) {
                throw new \Exception('Không có gì để cập nhật', 400);
            }
    
            if (!empty($updateData)) {
                $affectedRows = CompanyModel::query()
                    ->where('CompanyID', $companyID)
                    ->update($updateData);
    
                if ($affectedRows === 0) {
                    throw new \Exception('Công ty không tồn tại', 404);
                }
            }
    
            if (!empty($companyData['Position'])) {
                $this->employerService->updateEmployer($companyID, $companyData['Position']);
            }
    
            return true;
        });
    }
}