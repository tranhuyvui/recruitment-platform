<?php

use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    // POST /api/resumes/generate-summary
    Route::post('/resumes/generate-summary', [ResumeController::class, 'generateSummaryWithAI'])
        ->name('resume.generate-summary');

    // GET /api/resumes
    Route::get('/resumes', [ResumeController::class, 'getMyResumes'])
        ->name('resume.my-resumes');

    // GET /api/resumes/of-me
    Route::get('/resumes/of-me', [ResumeController::class, 'getListResumes'])
        ->name('resume.of-me');

    // POST /api/resumes/build
    Route::post('/resumes/build', [ResumeController::class, 'createManualResume'])
        ->name('resume.create-manual');

    // GET /api/resumes/employer/{resumeId}
    Route::get('/resumes/employer/{resumeId}', [ResumeController::class, 'getResumeDetailByEmployer'])
        ->name('resume.employer-detail');

    // GET /api/resumes/detail/{resumeId}
    Route::get('/resumes/detail/{resumeId}', [ResumeController::class, 'getResumeDetail'])
        ->name('resume.detail');

    // PUT /api/resumes/{resumeId}
    Route::put('/resumes/{resumeId}', [ResumeController::class, 'updateManualResume'])
        ->name('resume.update-manual');

    // DELETE /api/resumes/{resumeId}
    Route::delete('/resumes/{resumeId}', [ResumeController::class, 'deleteResume'])
        ->name('resume.delete');
});