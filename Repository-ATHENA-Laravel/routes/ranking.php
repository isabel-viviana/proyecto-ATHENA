<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Ranking\RankingController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [RankingController::class, 'index']);
});
