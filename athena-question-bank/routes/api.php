<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Admin\DocumentController;
use App\Http\Controllers\Api\V1\Admin\ExtractionController;

/*
|--------------------------------------------------------------------------
| API Routes — ATHENA Question Bank
|--------------------------------------------------------------------------
| Rutas públicas y de servicio para el microservicio de banco de preguntas.
*/

Route::prefix('v1')->group(function () {
    // Endpoint público de salud
    Route::get('/health', HealthController::class);

    /*
    |--------------------------------------------------------------------------
    | Rutas Administrativas (Documentos, Extracción y Gestión)
    |--------------------------------------------------------------------------
    | Nota de arquitectura: Estas rutas están identificadas para administración.
    | Quedan pendientes de protección mediante middleware de autenticación
    | M2M (HMAC-SHA256) en la fase de seguridad.
    */
    Route::prefix('admin')->group(function () {
        Route::prefix('documents')->group(function () {
            Route::get('/', [DocumentController::class, 'index']);
            Route::post('/', [DocumentController::class, 'store']);
            Route::get('/{id}', [DocumentController::class, 'show']);
            Route::delete('/{id}', [DocumentController::class, 'destroy']);

            // Endpoints de Extracción con Gemini
            Route::post('/{id}/extract', [ExtractionController::class, 'extract']);
            Route::get('/{id}/extraction-status', [ExtractionController::class, 'status']);
            Route::get('/{id}/extracted-questions', [ExtractionController::class, 'questions']);
        });
    });
});

