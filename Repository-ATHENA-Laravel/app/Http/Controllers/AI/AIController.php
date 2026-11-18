<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\SendMessageRequest;
use App\Services\AiService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AIController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Centraliza la obtención del ID del usuario autenticado mediante Sanctum sin fallback a usuario fijo.
     *
     * @throws AuthenticationException
     */
    protected function getUserId(): int
    {
        $userId = Auth::guard('sanctum')->id() ?? Auth::id();

        if ($userId === null) {
            throw new AuthenticationException('No autenticado.');
        }

        return (int) $userId;
    }

    /**
     * POST /api/ai/message
     * Procesa una pregunta o mensaje del estudiante hacia ATHENA IA.
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $validated = $request->validated();
            $result = $this->aiService->sendMessage(
                $userId,
                $validated['message'],
                $validated['conversation_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Respuesta de ATHENA generada correctamente.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la interacción con ATHENA: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/ai/history
     * Obtiene el listado de conversaciones de ATHENA del estudiante.
     */
    public function history(): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->aiService->getUserConversations($userId);

            return response()->json([
                'success' => true,
                'message' => 'Historial de conversaciones obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el historial de conversaciones: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/ai/conversations/{id}
     * Recupera los mensajes de una conversación concreta.
     */
    public function showConversation(string $id): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->aiService->getConversation($userId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Detalle de conversación obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la conversación: ' . $e->getMessage(),
                'errors' => [],
            ], 404);
        }
    }
}
