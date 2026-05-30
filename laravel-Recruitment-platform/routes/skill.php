<?php

use App\Http\Controllers\SkillController;
use Illuminate\Support\Facades\Route;

Route::get('/skills', [SkillController::class, 'getAllSkills'])->name('skills.list');
Route::get('/skills/{id}', [SkillController::class, 'getSkillById'])->name('skills.detail');
Route::post('/skills', [SkillController::class, 'createSkill'])->name('skills.create');
Route::put('/skills/{id}', [SkillController::class, 'updateSkill'])->name('skills.update');
Route::delete('/skills/{id}', [SkillController::class, 'deleteSkill'])->name('skills.delete');
