<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/unauthorized', function () {
    return response()->json([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập hoặc token không hợp lệ.'
    ], 401);
})->name('login');

Route::post('/users/login', [AuthController::class, 'login']);

require __DIR__ . '/company.php';
require __DIR__ . '/employer.php';
require __DIR__.'/jobApplication.php';
require __DIR__.'/savedJob.php';
require __DIR__.'/auth.php';
require __DIR__.'/message.php';
require __DIR__ . '/candidate.php';
require __DIR__ . '/resume.php';

