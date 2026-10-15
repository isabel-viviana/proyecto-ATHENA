<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TopicController;

Route::get('/', [TopicController::class, 'index']);
Route::post('/', [TopicController::class, 'store']);
Route::get('/{id}', [TopicController::class, 'show']);
Route::put('/{id}', [TopicController::class, 'update']);
Route::delete('/{id}', [TopicController::class, 'destroy']);
