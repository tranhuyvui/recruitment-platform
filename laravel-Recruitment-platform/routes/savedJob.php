<?php

use App\Http\Controllers\SavedJobController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    // GET /api/saved-job
    Route::get('/saved-job', [SavedJobController::class, 'getMySavedJobs']);

    // GET /api/saved-job/{jobId}
    Route::get('/saved-job/{jobId}', [SavedJobController::class, 'isSavedJob']);

    // POST /api/saved-job/{jobId}
    Route::post('/saved-job/{jobId}', [SavedJobController::class, 'savedJob']);

    // DELETE /api/saved-job/unsave-job/{jobId}
    Route::delete('/saved-job/unsave-job/{jobId}', [SavedJobController::class, 'removeSavedJob']);
});