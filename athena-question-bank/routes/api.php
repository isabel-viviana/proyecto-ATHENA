<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Admin\DocumentController;
use App\Http\Controllers\Api\V1\Admin\ExtractionController;
use App\Http\Controllers\Api\V1\Admin\QuestionController;
use App\Http\Controllers\Api\V1\Admin\CatalogController;
use App\Http\Controllers\Api\V1\Admin\MediaController;

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
    | Rutas Administrativas (Documentos, Extracción, Catálogos y Revisión Pedagógica)
    |--------------------------------------------------------------------------
    | Nota de arquitectura: Estas rutas están identificadas para administración.
    | Quedan pendientes de protección mediante middleware de autenticación
    | M2M (HMAC-SHA256) en la fase de seguridad.
    */
    Route::prefix('admin')->group(function () {
        // Catálogos ICFES
        Route::prefix('catalogs')->group(function () {
            Route::get('/subjects', [CatalogController::class, 'subjects']);
            Route::get('/topics', [CatalogController::class, 'topics']);
        });

        // Gestión de Documentos Fuente PDF
        Route::prefix('documents')->group(function () {
            Route::get('/', [DocumentController::class, 'index']);
            Route::post('/', [DocumentController::class, 'store']);
            Route::get('/{id}', [DocumentController::class, 'show']);
            Route::delete('/{id}', [DocumentController::class, 'destroy']);

            // Endpoints de Extracción con Gemini
            Route::post('/{id}/extract', [ExtractionController::class, 'extract']);
            Route::get('/{id}/extraction-status', [ExtractionController::class, 'status']);
            Route::get('/{id}/extracted-questions', [ExtractionController::class, 'questions']);
            Route::get('/{id}/media', [MediaController::class, 'documentMedia']);
        });

        // Recursos visuales (stream desde bucket privado icfes-media y gestión de asociaciones)
        Route::get('/media', [MediaController::class, 'show']);
        Route::prefix('media-assets')->group(function () {
            Route::get('/', [MediaController::class, 'index']);
            Route::post('/{id}/associate', [MediaController::class, 'associate']);
            Route::post('/{id}/disassociate', [MediaController::class, 'disassociate']);
            Route::delete('/{id}/association', [MediaController::class, 'disassociate']);
        });

        // Revisión y Calibración Pedagógica de Reactivos
        Route::prefix('questions')->group(function () {
            Route::get('/', [QuestionController::class, 'index']);
            Route::get('/{id}', [QuestionController::class, 'show']);
            Route::put('/{id}', [QuestionController::class, 'update']);
            Route::post('/{id}/approve', [QuestionController::class, 'approve']);
            Route::post('/{id}/reject', [QuestionController::class, 'reject']);
        });
    });
});

