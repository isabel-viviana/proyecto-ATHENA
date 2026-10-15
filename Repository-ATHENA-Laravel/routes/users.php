<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

Route::get('/', [UserController::class, 'index']);
Route::post('/', [UserController::class, 'store']);
Route::get('/{id}', [UserController::class, 'show']);
Route::put('/{id}', [UserController::class, 'update']);
Route::delete('/{id}', [UserController::class, 'destroy']);
Route::patch('/{id}/role', [UserController::class, 'changeRole']);
Route::patch('/{id}/status', [UserController::class, 'toggleStatus']);
