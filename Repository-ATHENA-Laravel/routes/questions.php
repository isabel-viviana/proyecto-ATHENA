<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\QuestionController;

Route::get('/', [QuestionController::class, 'index']);
Route::post('/', [QuestionController::class, 'store']);
Route::get('/{id}', [QuestionController::class, 'show']);
Route::put('/{id}', [QuestionController::class, 'update']);
Route::delete('/{id}', [QuestionController::class, 'destroy']);
