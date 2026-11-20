<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestDbController;
use App\Http\Controllers\Auth\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('test-db')->group(function () {
    Route::get('/integrity', [TestDbController::class, 'verifyIntegrity']);
    Route::get('/academic', [TestDbController::class, 'verifyAcademicStructure']);
    Route::get('/simulators', [TestDbController::class, 'verifySimulators']);
    Route::get('/commercial-ai', [TestDbController::class, 'verifyCommercialAndAi']);
    Route::get('/hybrid', [TestDbController::class, 'verifyHybridDatabase']);
    Route::get('/gemini', [TestDbController::class, 'testGeminiConnection']);
});

// Autenticación pública y protegida
Route::prefix('auth')->group(base_path('routes/auth.php'));

// Endpoint estándar de sesión verificada
Route::middleware(['auth:sanctum', 'active'])->get('/user', [AuthController::class, 'me']);

// Rutas Administrativas (RBAC estricto: solo superadmin, admin y support activos)
Route::middleware(['auth:sanctum', 'active', 'role:superadmin,super_admin,admin,support'])->group(function () {
    Route::prefix('users')->group(base_path('routes/users.php'));
    Route::prefix('subjects')->group(base_path('routes/subjects.php'));
    Route::prefix('topics')->group(base_path('routes/topics.php'));
    Route::prefix('questions')->group(base_path('routes/questions.php'));
});

// Rutas de Estudiante / Plataforma Académica (RBAC + sesión activa verificada)
Route::middleware(['auth:sanctum', 'active', 'role:student,superadmin,super_admin,admin,support'])->group(function () {
    Route::prefix('exams')->group(base_path('routes/exams.php'));
    Route::prefix('progress')->group(base_path('routes/progress.php'));
    Route::prefix('profile')->group(base_path('routes/profile.php'));
    Route::prefix('ai')->group(base_path('routes/ai.php'));
    Route::prefix('notifications')->group(base_path('routes/notifications.php'));
});

// Suscripciones y Webhook Wompi
Route::prefix('subscriptions')->group(base_path('routes/subscriptions.php'));
