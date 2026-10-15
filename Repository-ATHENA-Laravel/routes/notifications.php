<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Notification\NotificationController;

Route::get('/', [NotificationController::class, 'index']);
Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
