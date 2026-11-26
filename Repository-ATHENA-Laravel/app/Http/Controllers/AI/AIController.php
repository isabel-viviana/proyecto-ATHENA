<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\SendMessageRequest;
use App\Services\AiService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Centraliza la obtención del ID del usuario autenticado mediante Sanctum sin fallback a usuario fijo.
     *
     * @throws AuthenticationException
     */
    protected function getUserId(?Request $request = null): int
    {
        $userId = $request?->user()?->id
            ?? Auth::guard('sanctum')->id()
            ?? Auth::id();

        if ($userId === null) {
            throw new AuthenticationException('No autenticado.');
        }

        return (int) $userId;
    }

    /**
     * POST /api/ai/message
     * Envía un mensaje a ATHENA IA asociándolo estrictamente al usuario autenticado.
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        $userId = $this->getUserId($request);

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
     * GET /api/ai/conversations y GET /api/ai/history
     * Lista únicamente las conversaciones que pertenecen al estudiante autenticado.
     */
    public function getConversations(Request $request): JsonResponse
    {
        $userId = $this->getUserId($request);

        try {
            $data = $this->aiService->getUserConversations($userId);

            return response()->json([
                'success' => true,
                'message' => 'Historial de conversaciones obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('Error en AIController::getConversations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el historial de conversaciones: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/ai/conversations/{id}
     * Retorna una conversación específica validando que pertenezca al usuario autenticado (Anti-IDOR).
     */
    public function getConversation(Request $request, $id): JsonResponse
    {
        $userId = $this->getUserId($request);

        try {
            $data = $this->aiService->getConversation($userId, (string) $id);

            return response()->json([
                'success' => true,
                'message' => 'Detalle de conversación obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('Error en AIController::getConversation: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la conversación: ' . $e->getMessage(),
                'errors' => [],
            ], 404);
        }
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
