<?php

use App\Http\Controllers\EmployerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    Route::put('/employers/{EmployerID}/status', [EmployerController::class, 'updateStatusEmployer'])
        ->name('employer.update-status');

    Route::get('/employers/status', [EmployerController::class, 'getPendingEmployers'])
        ->name('employer.status');

    Route::get('/employers/dashboard-stats', [EmployerController::class, 'getDashboardStats'])
        ->name('employer.dashboard-stats');

    Route::get('/employers/pending', [EmployerController::class, 'getPendingEmployers'])
        ->name('employer.pending');

    Route::get('/employers/top-employers', [EmployerController::class, 'getTopEmployers'])
        ->name('employer.top-employers');

    Route::get('/employers/all-employers', [EmployerController::class, 'getAllEmployers'])
        ->name('employer.all-employers');
});


Route::get('/employers/logo-top-employers', [EmployerController::class, 'getLogoTopEmployers'])
    ->name('employer.logo-top-employers');