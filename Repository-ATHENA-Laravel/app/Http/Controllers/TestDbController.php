<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Subject;
use App\Models\ExamAttempt;

use Illuminate\Http\Request;

class TestDbController extends Controller
{


    /**
     * Verificar integridad del usuario principal y sus relaciones.
     */
    public function verifyIntegrity(): JsonResponse
    {
        $user = User::where('full_name', 'Juan Sebastian Acevedo Medina')->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuario principal no encontrado. Ejecuta el seeder primero.'
            ], 404);
        }

        $userWithCounts = User::where('id', $user->id)
            ->withCount([
                'examConfigs',
                'examAttempts',
                'userTopicPerformances',
                'userAchievements',
                'notifications',
                'subscriptions',
                'aiChatSessions'
            ])->first();

        return response()->json([
            'status' => 'success',
            'modulo' => 'Verificación de Integridad de Relaciones',
            'perfil_evaluado' => [
                'id' => $userWithCounts->id,
                'nombre' => $userWithCounts->full_name,
                'email' => $userWithCounts->email,
                'xp_actual' => $userWithCounts->xp,
                'monedas' => $userWithCounts->coins,
            ],
            'conteos_relacionales_eloquent' => [
                'configuraciones_de_examenes' => $userWithCounts->exam_configs_count,
                'intentos_de_simulacros' => $userWithCounts->exam_attempts_count,
                'rendimientos_por_tema' => $userWithCounts->user_topic_performances_count,
                'logros_desbloqueados' => $userWithCounts->user_achievements_count,
                'notificaciones_enviadas' => $userWithCounts->notifications_count,
                'suscripciones_asociadas' => $userWithCounts->subscriptions_count,
                'sesiones_chat_ia' => $userWithCounts->ai_chat_sessions_count,
            ]
        ]);
    }

    /**
     * Validar el árbol académico (Materias -> Temas -> Preguntas).
     */
    public function verifyAcademicStructure(): JsonResponse
    {
        $academicTree = Subject::with(['topics.questions.options'])->get();

        return response()->json([
            'status' => 'success',
            'modulo' => 'Estructura del Banco de Preguntas ICFES',
            'total_materias' => $academicTree->count(),
            'datos' => $academicTree->map(fn($subject) => [
                'materia' => $subject->name,
                'slug' => $subject->slug,
                'total_temas' => $subject->topics->count(),
                'temas' => $subject->topics->map(fn($topic) => [
                    'nombre_tema' => $topic->name,
                    'total_preguntas' => $topic->questions->count(),
                    'ejemplo_pregunta' => $topic->questions->map(fn($q) => [
                        'enunciado' => $q->statement,
                        'dificultad' => $q->difficulty,
                        'explicacion_icfes' => $q->explanation,
                        'opciones' => $q->options->map(fn($o) => [
                            'letra' => $o->option_letter,
                            'texto' => $o->option_text,
                            'es_correcta' => $o->is_correct
                        ])
                    ])->take(1)
                ])
            ])
        ]);
    }

    /**
     * Historial de simulacros aplicados del estudiante.
     */
    public function verifySimulators(): JsonResponse
    {
        $user = User::where('full_name', 'Juan Sebastian Acevedo Medina')->first();

        if (!$user) return response()->json(['message' => 'Usuario no encontrado'], 404);

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->with(['examConfig', 'answers.question'])
            ->get();

        return response()->json([
            'status' => 'success',
            'modulo' => 'Historial de Simulacros y Respuestas Adaptativas',
            'total_intentos_registrados' => $attempts->count(),
            'intentos' => $attempts->map(fn($attempt) => [
                'intento_id' => $attempt->id,
                'tipo_examen' => $attempt->examConfig->exam_type ?? 'No definido',
                'dificultad_configurada' => $attempt->examConfig->difficulty ?? 'No definido',
                'fecha_creacion' => $attempt->created_at->toDateTimeString(),
                'respuestas_entregadas' => $attempt->answers->map(fn($ans) => [
                    'pregunta_id' => $ans->question_id,
                    'enunciado_pregunta' => $ans->question->statement ?? 'N/A',
                    'es_correcta' => $ans->is_correct,
                    'tiempo_empleado_segundos' => $ans->time_spent_seconds,
                    'vio_explicacion' => $ans->explanation_viewed
                ])
            ])
        ]);
    }

    /**
     * Diagnóstico de pasarela comercial y mensajería del Tutor IA.
     */
    public function verifyCommercialAndAi(): JsonResponse
    {
        $user = User::where('full_name', 'Juan Sebastian Acevedo Medina')->first();

        if (!$user) return response()->json(['message' => 'Usuario no encontrado'], 404);

        $fullUserContext = User::where('id', $user->id)
            ->with([
                'subscriptions.subscriptionPlan',
                'subscriptions.paymentTransactions',
                'aiChatSessions.aiChatMessages'
            ])->first();

        return response()->json([
            'status' => 'success',
            'modulo' => 'Suscripciones, Pagos y Mensajería con IA',
            'monetizacion' => $fullUserContext->subscriptions->map(fn($sub) => [
                'plan' => $sub->subscriptionPlan->name,
                'precio' => $sub->subscriptionPlan->price,
                'estado' => $sub->status,
                'vence_el' => $sub->end_date,
                'transacciones_pago' => $sub->paymentTransactions->map(fn($tx) => [
                    'referencia_id' => $tx->id,
                    'monto_pagado' => $tx->amount,
                    'estado_pago' => $tx->status
                ])
            ]),
            'chats_con_tutor_ia' => $fullUserContext->aiChatSessions->map(fn($session) => [
                'sesion_titulo' => $session->title,
                'conversacion' => $session->aiChatMessages->map(fn($msg) => [
                    'remitente' => $msg->sender_type,
                    'mensaje' => $msg->message,
                    'hora' => $msg->created_at->toTimeString()
                ])
            ])
        ]);
    }

    /**
     * Prueba mínima de verificación de arquitectura híbrida (MySQL + MongoDB Atlas).
     */
    public function verifyHybridDatabase(): JsonResponse
    {
        $mysqlStatus = 'unknown';
        $userCount = 0;
        try {
            \Illuminate\Support\Facades\DB::connection('mysql')->getPdo();
            $userCount = User::count();
            $mysqlStatus = 'connected';
        } catch (\Throwable $e) {
            $mysqlStatus = 'error: ' . $e->getMessage();
        }

        $mongoStatus = 'unknown';
        $testResult = null;
        try {
            $testDoc = \App\Models\Mongo\AiConversation::create([
                'user_id' => 1,
                'title' => 'Conversación de prueba híbrida',
                'messages' => [
                    ['sender' => 'user', 'text' => 'Hola ATHENA']
                ],
                'context' => ['test' => true],
            ]);

            $foundDoc = \App\Models\Mongo\AiConversation::find($testDoc->_id);
            $readSuccess = $foundDoc !== null && $foundDoc->title === 'Conversación de prueba híbrida';

            $testDoc->delete();
            $deletedDoc = \App\Models\Mongo\AiConversation::find($testDoc->_id);
            $deleteSuccess = $deletedDoc === null;

            $mongoStatus = 'connected_and_verified';
            $testResult = [
                'write' => true,
                'read' => $readSuccess,
                'delete' => $deleteSuccess,
            ];
        } catch (\Throwable $e) {
            $mongoStatus = 'pending_credentials_or_unreachable: ' . $e->getMessage();
        }

        return response()->json([
            'status' => 'success',
            'modulo' => 'Verificación de Arquitectura Híbrida (MySQL + MongoDB Atlas)',
            'mysql' => [
                'status' => $mysqlStatus,
                'total_usuarios_mysql' => $userCount,
            ],
            'mongodb' => [
                'status' => $mongoStatus,
                'prueba_crud_temporal' => $testResult,
            ]
        ]);
    }

    /**
     * GET /api/test-db/gemini
     * Diagnóstico de conexión real con la API de Google Gemini Flash.
     */
    public function testGeminiConnection(): JsonResponse
    {
        try {
            $geminiService = app(\App\Services\GeminiService::class);
            $responseText = $geminiService->generateText("Responde únicamente: ATHENA Gemini funcionando.");

            return response()->json([
                'success' => true,
                'message' => 'Gemini conectado correctamente.',
                'data' => [
                    'response' => $responseText,
                ],
            ]);
        } catch (\Throwable $e) {
            $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
            $safeMessage = preg_replace('/key=[a-zA-Z0-9_\-]+/', 'key=***HIDDEN***', $e->getMessage());
            if (!empty($apiKey)) {
                $safeMessage = str_replace($apiKey, '***HIDDEN***', $safeMessage);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con Gemini: ' . $safeMessage,
                'errors' => [],
            ], 500);
        }
    }
}
