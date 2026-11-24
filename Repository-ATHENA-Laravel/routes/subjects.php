<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SubjectController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [SubjectController::class, 'index']);
    Route::get('/{id}', [SubjectController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:admin,superadmin,super_admin'])->group(function () {
    Route::post('/', [SubjectController::class, 'store']);
    Route::put('/{id}', [SubjectController::class, 'update']);
    Route::delete('/{id}', [SubjectController::class, 'destroy']);
});
