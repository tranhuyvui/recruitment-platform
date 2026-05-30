<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/users/login', [AuthController::class, 'login']);

require __DIR__ . '/company.php';
require __DIR__ . '/employer.php';
require __DIR__.'/auth.php';
require __DIR__.'/message.php';
require __DIR__ . '/candidate.php';
require __DIR__ . '/skill.php';
require __DIR__ . '/jobskill.php';