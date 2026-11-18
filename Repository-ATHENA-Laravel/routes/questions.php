<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\QuestionController;

Route::middleware(['auth:sanctum', 'role:admin,superadmin,super_admin'])->group(function () {
    Route::get('/', [QuestionController::class, 'index']);
    Route::post('/', [QuestionController::class, 'store']);
    Route::get('/{id}', [QuestionController::class, 'show']);
    Route::put('/{id}', [QuestionController::class, 'update']);
    Route::delete('/{id}', [QuestionController::class, 'destroy']);
});
