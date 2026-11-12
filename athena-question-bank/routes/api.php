<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\CatalogController;
use App\Http\Controllers\Api\V1\Admin\DocumentController;
use App\Http\Controllers\Api\V1\Admin\ExtractionController;
use App\Http\Controllers\Api\V1\Admin\IqpeReviewController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\Admin\QuestionController;
use App\Http\Controllers\Api\V1\Student\StudentQuestionController;
use App\Http\Controllers\Api\V1\Student\StudentAuthController;
use App\Http\Controllers\Api\V1\Student\StudentProfileController;
use App\Http\Controllers\Api\V1\Student\StudentRankingController;
use App\Http\Controllers\Api\V1\Student\StudentNotificationController;
use App\Http\Controllers\Api\V1\Student\StudentExamController;

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
        // Autenticación administrativa
        Route::prefix('auth')->group(function () {
            Route::post('/login', [AdminAuthController::class, 'login']);
            Route::get('/me', [AdminAuthController::class, 'me'])->middleware('admin.auth');
        });

        // Rutas protegidas bajo middleware 'admin.auth'
        Route::middleware('admin.auth')->group(function () {
            // Catálogos ICFES
            Route::prefix('catalogs')->group(function () {
                Route::get('/subjects', [CatalogController::class, 'subjects']);
                Route::get('/topics', [CatalogController::class, 'topics']);
            });

            // Gestión de Documentos Fuente PDF y Orquestador IQPE
            Route::prefix('documents')->group(function () {
                Route::get('/', [DocumentController::class, 'index']);
                Route::post('/', [DocumentController::class, 'store']);
                Route::get('/{id}', [DocumentController::class, 'show']);
                Route::delete('/{id}', [DocumentController::class, 'destroy']);

                // Endpoints de Orquestador Asíncrono IQPE
                Route::post('/{id}/extract', [ExtractionController::class, 'extract']);
                Route::get('/{id}/iqpe-status', [ExtractionController::class, 'iqpeStatus']);
                Route::get('/{id}/extraction-status', [ExtractionController::class, 'status']);
                Route::post('/{id}/retry', [ExtractionController::class, 'retry']);
                Route::post('/{id}/retry-resolutions', [ExtractionController::class, 'retryResolutions']);
                Route::get('/{id}/extracted-questions', [ExtractionController::class, 'questions']);
                Route::get('/{id}/audit', [ExtractionController::class, 'audit']);
                Route::post('/{id}/audit', [ExtractionController::class, 'audit']);
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

            // Bandeja Unificada de Revisión IQPE
            Route::get('/iqpe/review-queue', [IqpeReviewController::class, 'reviewQueue']);

            // Revisión y Calibración Pedagógica de Reactivos
            Route::prefix('questions')->group(function () {
                Route::get('/', [QuestionController::class, 'index']);
                Route::post('/bulk-approve', [QuestionController::class, 'bulkApprove']);
                Route::get('/{id}', [QuestionController::class, 'show']);
                Route::get('/{id}/iqpe-detail', [IqpeReviewController::class, 'detail']);
                Route::put('/{id}', [QuestionController::class, 'update']);
                Route::post('/{id}/approve', [QuestionController::class, 'approve']);
                Route::post('/{id}/reject', [QuestionController::class, 'reject']);
                Route::post('/{id}/return-to-review', [IqpeReviewController::class, 'returnToReview']);
                Route::post('/{id}/publish', [IqpeReviewController::class, 'publish']);
            });
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Rutas de Estudiante (Autenticación, Práctica, Resolución y Progreso)
    |--------------------------------------------------------------------------
    */
    Route::prefix('student')->group(function () {
        // Autenticación segura de estudiantes (integrada con usuarios ATHENA)
        Route::prefix('auth')->group(function () {
            Route::post('/login', [StudentAuthController::class, 'login']);
            Route::get('/me', [StudentAuthController::class, 'me']);
        });

        // Catálogo de materias con preguntas publicadas
        Route::get('/subjects', [StudentQuestionController::class, 'subjects']);

        // Banco de preguntas y calificación
        Route::prefix('questions')->group(function () {
            Route::get('/', [StudentQuestionController::class, 'index']);
            Route::get('/{id}', [StudentQuestionController::class, 'show']);
            Route::post('/{id}/answer', [StudentQuestionController::class, 'submitAnswer']);
        });

        // Perfil institucional del estudiante
        Route::prefix('profile')->group(function () {
            Route::get('/', [StudentProfileController::class, 'show']);
            Route::put('/', [StudentProfileController::class, 'update']);
            Route::post('/password', [StudentProfileController::class, 'changePassword']);
        });

        // Métricas de desempeño y progreso del estudiante
        Route::get('/progress', [StudentQuestionController::class, 'progress']);

        // Ranking institucional de estudiantes
        Route::prefix('ranking')->group(function () {
            Route::get('/', [StudentRankingController::class, 'index']);
            Route::get('/me', [StudentRankingController::class, 'me']);
        });

        // Bandeja de notificaciones del estudiante
        Route::prefix('notifications')->group(function () {
            Route::get('/', [StudentNotificationController::class, 'index']);
            Route::get('/unread-count', [StudentNotificationController::class, 'unreadCount']);
            Route::patch('/read-all', [StudentNotificationController::class, 'markAllAsRead']);
            Route::patch('/{id}/read', [StudentNotificationController::class, 'markAsRead']);
            Route::delete('/{id}', [StudentNotificationController::class, 'destroy']);
        });

        // Simulacros estructurados (Mock Exams)
        Route::prefix('exams')->group(function () {
            Route::get('/config-options', [StudentExamController::class, 'configOptions']);
            Route::post('/start', [StudentExamController::class, 'start']);
            Route::get('/active', [StudentExamController::class, 'active']);
            Route::get('/history', [StudentExamController::class, 'history']);
            Route::get('/{id}', [StudentExamController::class, 'show']);
            Route::post('/{id}/answers', [StudentExamController::class, 'saveAnswer']);
            Route::post('/{id}/finish', [StudentExamController::class, 'finish']);
            Route::get('/{id}/results', [StudentExamController::class, 'results']);
        });
    });

    // Endpoint público para streaming de imágenes asociadas a preguntas y estímulos
    Route::get('/media', [MediaController::class, 'show']);
});

