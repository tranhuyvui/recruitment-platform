<?php

use App\Http\Controllers\EmployerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    Route::put('/employer/{EmployerID}/status', [EmployerController::class, 'updateStatusEmployer'])
        ->name('employer.update-status');

    Route::get('/employer/status', [EmployerController::class, 'getPendingEmployers'])
        ->name('employer.status');

    Route::get('/employer/dashboard-stats', [EmployerController::class, 'getDashboardStats'])
        ->name('employer.dashboard-stats');

    Route::get('/employer/pending', [EmployerController::class, 'getPendingEmployers'])
        ->name('employer.pending');

    Route::get('/employer/top-employers', [EmployerController::class, 'getTopEmployers'])
        ->name('employer.top-employers');

    Route::get('/employer/all-employers', [EmployerController::class, 'getAllEmployers'])
        ->name('employer.all-employers');
});


Route::get('/employer/logo-top-employers', [EmployerController::class, 'getLogoTopEmployers'])
    ->name('employer.logo-top-employers');