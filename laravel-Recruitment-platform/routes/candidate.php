<?php

use App\Http\Controllers\CandidateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('/candidates/profile', [CandidateController::class, 'getProfile']);

    Route::get('/candidates/info', [CandidateController::class, 'getCandidateInfo']);

    Route::post('/candidates/profile', [CandidateController::class, 'upsertProfile'])
        ->name('candidates.profile.upsert');

    Route::put('/candidates/profile-detail', [CandidateController::class, 'updateMasterProfileDetail'])
        ->name('candidates.profile.detail');

    Route::get('/candidates/skills', [CandidateController::class, 'getSkills']);

    Route::post('/candidates/skills/analyze-text', [CandidateController::class, 'analyzeSkillsText'])
        ->name('candidates.skills.analyze');

    Route::post('/candidates/skills', [CandidateController::class, 'saveAnalyzedSkills'])
        ->name('candidates.skills.save');

    Route::middleware('auth:api')->group(function () {
        
        Route::get('/candidates/employer/list', [CandidateController::class, 'getCandidatesForEmployer'])
            ->name('employer.candidates.list');

        Route::get('/candidates/employer/detail/{id}', [CandidateController::class, 'getCandidateDetailForEmployer'])
            ->name('employer.candidates.detail');
    });

    Route::middleware('auth:api')->group(function () {
        
        Route::get('/candidates/admin/all-candidates', [CandidateController::class, 'getAllCandidates']);
    });
   
    // Route::middleware('role:Employer')->group(function () {
        
    //     // GET /candidates/employer/list
    //     Route::get('/candidates/employer/list', [CandidateController::class, 'getCandidatesForEmployer'])
    //         ->name('employer.candidates.list');

    //     // GET /candidates/employer/detail/{id}
    //     // (Route động {id} đặt ở dưới cùng của nhóm Employer)
    //     Route::get('/candidates/employer/detail/{id}', [CandidateController::class, 'getCandidateDetailForEmployer'])
    //         ->name('employer.candidates.detail');
    // });

    // Route::middleware('role:Admin')->group(function () {
        
    //     // GET /candidates/admin/all-candidates
    //     Route::get('/candidates/admin/all-candidates', [CandidateController::class, 'getAllCandidates']);
    // });

});