<?php

use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\WompiWebhookController;
use App\Http\Controllers\TestDbController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Aquí centralizamos las rutas de diagnóstico y pruebas del sistema.
| Se utiliza un prefijo 'test-db' para mantener el orden.
*/

Route::prefix('test-db')->group(function () {
    // Diagnóstico de integridad de perfiles
    Route::get('/integrity', [TestDbController::class, 'verifyIntegrity']);

    // Diagnóstico de estructura académica (Banco de preguntas)
    Route::get('/academic', [TestDbController::class, 'verifyAcademicStructure']);

    // Diagnóstico de simulacros e historial
    Route::get('/simulators', [TestDbController::class, 'verifySimulators']);

    // Diagnóstico de monetización e IA
    Route::get('/commercial-ai', [TestDbController::class, 'verifyCommercialAndAi']);

    // Diagnóstico de arquitectura híbrida (MySQL + MongoDB Atlas)
    Route::get('/hybrid', [TestDbController::class, 'verifyHybridDatabase']);

    // Diagnóstico de conectividad con Gemini Flash API
    Route::get('/gemini', [TestDbController::class, 'testGeminiConnection']);
});

// Modular System Routes (Phase 0 alignment)
Route::prefix('auth')->group(base_path('routes/auth.php'));
Route::prefix('users')->group(base_path('routes/users.php'));
Route::prefix('subjects')->group(base_path('routes/subjects.php'));
Route::prefix('topics')->group(base_path('routes/topics.php'));
Route::prefix('questions')->group(base_path('routes/questions.php'));
Route::prefix('exams')->group(base_path('routes/exams.php'));
Route::prefix('progress')->group(base_path('routes/progress.php'));
Route::prefix('profile')->group(base_path('routes/profile.php'));
Route::prefix('ai')->group(base_path('routes/ai.php'));
Route::prefix('notifications')->group(base_path('routes/notifications.php'));

Route::prefix('payments')->group(function () {
    Route::post('/webhook', [WompiWebhookController::class, 'handle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/plans', [PaymentController::class, 'index']);
        Route::post('/checkout', [PaymentController::class, 'store']);
        Route::get('/subscriptions/{subscriptionId}', [PaymentController::class, 'show']);
    });
});
