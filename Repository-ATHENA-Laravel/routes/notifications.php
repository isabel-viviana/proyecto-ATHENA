<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Notification\NotificationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
});
