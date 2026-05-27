<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/users/login', [AuthController::class, 'login']);
Route::post('/users/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/users/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/users/register', [AuthController::class, 'register']);
Route::post('/users/refresh-token', [AuthController::class, 'refreshToken']);
Route::get('/users/profile', [AuthController::class, 'getProfile'])->middleware('auth:api');

Route::post('/users/request-otp-forgot', [AuthController::class, 'requestOtpForgotPassword']);
Route::post('/users/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/users/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api');
Route::post('/users/request-otp-auth', [AuthController::class, 'requestOtpAuth'])->middleware('auth:api');
Route::post('/users/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::delete('/users/delete-account', [AuthController::class, 'deleteAccount'])->middleware('auth:api');
Route::get('/users/current-role', [AuthController::class, 'getCurrentRole'])->middleware('auth:api');