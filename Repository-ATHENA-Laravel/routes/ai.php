<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\AIController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/message', [AIController::class, 'sendMessage']);
    Route::get('/conversations', [AIController::class, 'getConversations']);
    Route::get('/history', [AIController::class, 'getConversations']);
    Route::get('/conversations/{id}', [AIController::class, 'getConversation']);
});
