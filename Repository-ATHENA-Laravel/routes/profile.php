<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Profile\ProfileController;

Route::get('/', [ProfileController::class, 'show']);
Route::put('/', [ProfileController::class, 'update']);
