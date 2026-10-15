<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exam\ExamController;

Route::post('/configure', [ExamController::class, 'configure']);
Route::get('/{id}/questions', [ExamController::class, 'getQuestions']);
Route::post('/{id}/answer', [ExamController::class, 'submitAnswer']);
Route::post('/{id}/finish', [ExamController::class, 'finish']);
Route::get('/{id}/results', [ExamController::class, 'results']);
