<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SubjectController;

Route::get('/', [SubjectController::class, 'index']);
Route::post('/', [SubjectController::class, 'store']);
Route::get('/{id}', [SubjectController::class, 'show']);
Route::put('/{id}', [SubjectController::class, 'update']);
Route::delete('/{id}', [SubjectController::class, 'destroy']);
