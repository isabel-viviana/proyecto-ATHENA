<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Progress\ProgressController;

Route::get('/', [ProgressController::class, 'dashboard']);
Route::get('/history', [ProgressController::class, 'history']);
Route::get('/stats', [ProgressController::class, 'stats']);
Route::get('/subject/{subjectId}', [ProgressController::class, 'performanceBySubject']);
Route::get('/topic/{topicId}', [ProgressController::class, 'performanceByTopic']);
