<?php

use App\Http\Controllers\JobSkillController;
use Illuminate\Support\Facades\Route;

Route::get('/job-skills/{jobId}', [JobSkillController::class, 'getJobSkills'])
    ->name('job-skills.list');

Route::middleware('auth:api')->group(function () {
    
    Route::post('/job-skills/{jobId}', [JobSkillController::class, 'syncJobSkills'])
        ->name('job-skills.sync');
        
    Route::delete('/job-skills/{jobId}/{skillId}', [JobSkillController::class, 'removeJobSkill'])
        ->name('job-skills.remove');
        
});