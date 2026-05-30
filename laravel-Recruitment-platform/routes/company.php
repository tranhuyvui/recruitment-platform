<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    // POST /api/company
    Route::post('/company', [CompanyController::class, 'createCompany'])
        ->name('companies.create');

    // POST /api/company/request
    Route::post('/company/{companyID}/request', [CompanyController::class, 'requestCompany'])
        ->name('companies.request');
    // PUT /api/company/{companyID}
    Route::post('/company/{companyID}', [CompanyController::class, 'updateCompany'])
        ->name('companies.update');

    // GET /api/company/Detail/ofme
    Route::get('/company/Detail/ofme', [CompanyController::class, 'getCompanyDetailOfMe']);

    // GET /api/company/me
    Route::get('/company/me', [CompanyController::class, 'getCompanyOfMe']);

    // Admin routes phải để trước /company/{companyID}
    // GET /api/company/admin/all
    Route::get('/company/admin/all', [CompanyController::class, 'getAllCompanyForAdmin']);

    // GET /api/company/admin/{companyID}
    Route::get('/company/admin/{companyID}', [CompanyController::class, 'getCompanyByIdForAdmin'])
        ->name('admin.companies.detail');

    // PUT /api/company/admin/{companyID}/status
    Route::put('/company/admin/{companyID}/status', [CompanyController::class, 'updateCompanyStatus'])
        ->name('companies.update-status');

    // GET /api/company/{companyID}
    // Route động để cuối cùng để không ăn nhầm /admin/all, /me, /Detail/ofme
    Route::get('/company/{companyID}', [CompanyController::class, 'getCompanyDetail'])
        ->name('companies.detail');

    // GET /api/company
    Route::get('/company', [CompanyController::class, 'getAllCompany']);
});