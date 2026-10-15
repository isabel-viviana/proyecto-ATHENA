<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\AIController;

Route::post('/message', [AIController::class, 'sendMessage']);
Route::get('/history', [AIController::class, 'history']);
Route::get('/conversations/{id}', [AIController::class, 'showConversation']);
