<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

Route::middleware(['auth:sanctum', 'role:admin,superadmin,super_admin,support'])->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::patch('/{id}/role', [UserController::class, 'changeRole']);
    Route::patch('/{id}/status', [UserController::class, 'toggleStatus']);
});
