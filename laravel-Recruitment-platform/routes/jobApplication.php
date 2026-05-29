<?php

use App\Http\Controllers\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::post('/job-application', [JobApplicationController::class, 'applyJob'])
        ->name('job-application.apply');
    Route::put('/job-application/{ApplicationID}/status', [JobApplicationController::class, 'updateApplicationStatus'])
        ->name('job-application.update-status');
    Route::get('/job-application/stats', [JobApplicationController::class, 'getChartStatsController'])
        ->name('job-application.stats');
    Route::get('/job-application/ofme', [JobApplicationController::class, 'getSubmittedApplications'])
        ->name('job-application.ofme');
    Route::get('/job-application/job/{JobID}', [JobApplicationController::class, 'getListApplicationByJobId'])
        ->name('job-application.list-by-job');
    Route::get('/job-application/{ApplicationID}', [JobApplicationController::class, 'getApplicationDetail'])
        ->name('job-application.detail');

});