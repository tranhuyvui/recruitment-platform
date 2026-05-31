<?php

use App\Http\Controllers\JobController;
use App\Http\Controllers\SearchAIController;
use Illuminate\Support\Facades\Route;

Route::prefix('jobs')->group(function () {
    Route::get('/', [JobController::class, 'getAllJobs']);
    Route::get('/job-categories', [JobController::class, 'getAllCategories']);
    Route::get('/search-by-category/{categoryId}', [JobController::class, 'searchJobByCategory']);
    Route::get('/search-ai', [SearchAIController::class, 'searchJobsAI']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/job-of-me', [JobController::class, 'getJobOfMe']);
        // Route::get('/recommended', [JobController::class, 'getRecommendedJobs']);
        Route::post('/create-job', [JobController::class, 'createJob']);
        Route::delete('/soft-delete-job/{id}', [JobController::class, 'closeJob']);
        Route::put('/update-job/{id}', [JobController::class, 'updateJob']);

        Route::prefix('admin')->group(function () {
            Route::put('/change-status-job/{id}', [JobController::class, 'changeStatusJob']);
            Route::get('/7-day-stats', [JobController::class, 'get7DayStats']);
            Route::get('/monthly-new-candidates', [JobController::class, 'getMonthlyStats']);
            Route::get('/top-jobs', [JobController::class, 'getJobForAdmin']);
            Route::get('/jobs-by-status', [JobController::class, 'getJobForAdminByStatus']);
        });
    });

    Route::get('/{id}', [JobController::class, 'getJobDetail']);
});
