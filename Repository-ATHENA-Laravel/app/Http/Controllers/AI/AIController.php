<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\SendMessageRequest;
use App\Models\AiChatSession;
use App\Models\Mongo\AiConversation;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * POST /api/ai/message
     * Envía un mensaje a ATHENA IA asociándolo estrictamente al usuario autenticado.
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()->id;
            $validated = $request->validated();

            $result = $this->aiService->sendMessage(
                $userId,
                $validated['message'],
                $validated['conversation_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Respuesta de ATHENA generada correctamente.',
                'conversation_id' => $result['conversation_id'] ?? null,
                'ai_response' => $result['ai_response'] ?? null,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Error en AIController::sendMessage: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error en la interacción con ATHENA: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/ai/conversations
     * Lista únicamente las conversaciones que pertenecen al estudiante autenticado.
     */
    public function getConversations(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $conversationsList = [];

        // 1. Consulta en MongoDB filtrando estrictamente por user_id del token
        try {
            $mongoConversations = AiConversation::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->get();

            foreach ($mongoConversations as $conv) {
                $serializedId = (string) ($conv->id ?? $conv->_id ?? $conv->getKey());
                if ($serializedId !== '') {
                    $conversationsList[] = [
                        '_id' => $serializedId,
                        'id' => $serializedId,
                        'title' => (string) ($conv->title ?: 'Conversación'),
                        'updated_at' => $conv->updated_at
                            ? $conv->updated_at->toIso8601String()
                            : now()->toIso8601String(),
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('Error leyendo conversaciones de MongoDB: ' . $e->getMessage());
        }

        // 2. Respaldo en PostgreSQL filtrando igualmente por user_id del token
        if (empty($conversationsList)) {
            try {
                $pgSessions = AiChatSession::where('user_id', $userId)
                    ->orderBy('updated_at', 'desc')
                    ->get();

                foreach ($pgSessions as $session) {
                    $conversationsList[] = [
                        '_id' => (string) $session->id,
                        'id' => (string) $session->id,
                        'title' => (string) ($session->title ?: 'Conversación'),
                        'updated_at' => $session->updated_at
                            ? $session->updated_at->toIso8601String()
                            : now()->toIso8601String(),
                    ];
                }
            } catch (Throwable $e) {
                Log::error('Error leyendo conversaciones de respaldo en PostgreSQL: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Historial de conversaciones obtenido correctamente.',
            'data' => array_values($conversationsList),
        ]);
    }

    /**
     * GET /api/ai/conversations/{id}
     * Retorna una conversación específica validando que pertenezca al usuario autenticado (Anti-IDOR).
     */
    public function getConversation(Request $request, $id): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $idStr = (string) $id;

        // 1. Consulta en MongoDB exigiendo simultáneamente _id y user_id
        try {
            $conversation = AiConversation::where('_id', $idStr)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                $conversation = AiConversation::where('id', $idStr)
                    ->where('user_id', $userId)
                    ->first();
            }

            if ($conversation) {
                $serializedId = (string) ($conversation->id ?? $conversation->_id ?? $conversation->getKey());
                $messages = is_string($conversation->messages)
                    ? (json_decode($conversation->messages, true) ?: [])
                    : ($conversation->messages ?? []);

                return response()->json([
                    'success' => true,
                    'message' => 'Detalle de conversación obtenido correctamente.',
                    'data' => [
                        '_id' => $serializedId,
                        'id' => $serializedId,
                        'title' => (string) ($conversation->title ?: 'Conversación'),
                        'messages' => array_values($messages),
                        'updated_at' => $conversation->updated_at
                            ? $conversation->updated_at->toIso8601String()
                            : null,
                    ],
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Error obteniendo conversación en MongoDB: ' . $e->getMessage());
        }

        // 2. Respaldo en PostgreSQL exigiendo simultáneamente id y user_id
        if (is_numeric($idStr)) {
            try {
                $session = AiChatSession::with('aiChatMessages')
                    ->where('id', (int) $idStr)
                    ->where('user_id', $userId)
                    ->first();

                if ($session) {
                    $messages = $session->aiChatMessages->map(fn ($m) => [
                        'role' => $m->sender_type === 'user' ? 'user' : 'model',
                        'content' => $m->message,
                        'created_at' => $m->created_at ? $m->created_at->toIso8601String() : null,
                    ])->values()->toArray();

                    return response()->json([
                        'success' => true,
                        'message' => 'Detalle de conversación obtenido correctamente.',
                        'data' => [
                            '_id' => (string) $session->id,
                            'id' => (string) $session->id,
                            'title' => (string) ($session->title ?: 'Conversación'),
                            'messages' => $messages,
                            'updated_at' => $session->updated_at
                                ? $session->updated_at->toIso8601String()
                                : null,
                        ],
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('Error obteniendo conversación de respaldo en PostgreSQL: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Conversación no encontrada o no tienes permisos para acceder a ella.',
            'errors' => [],
        ], 404);
    }

    public function history(Request $request): JsonResponse
    {
        return $this->getConversations($request);
    }

    public function showConversation(Request $request, string $id): JsonResponse
    {
        return $this->getConversation($request, $id);
    }
}
