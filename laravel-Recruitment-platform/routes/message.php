<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('messages')->group(function () {
    Route::post('/', [ChatController::class, 'sendMessage']);
    Route::put('/read', [ChatController::class, 'markAsRead']);
    Route::get('/conversation', [ChatController::class, 'getConversations']);
    Route::get('/chat-history/{otherUserId}', [ChatController::class, 'getChatHistory']);
    Route::get('/unread-count', [ChatController::class, 'getCountUnreadMessages']);
});