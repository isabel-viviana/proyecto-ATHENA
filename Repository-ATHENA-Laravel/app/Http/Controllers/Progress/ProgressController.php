<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ProgressController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Obtiene el ID del usuario autenticado mediante Sanctum sin fallback a usuario fijo.
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
     * GET /api/progress
     * Dashboard general de progreso académico del estudiante.
     */
    public function dashboard(): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->progressService->getDashboardOverview($userId);

            return response()->json([
                'success' => true,
                'message' => 'Resumen de progreso obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el resumen de progreso: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/progress/history
     * Historial de simulacros completados del usuario.
     */
    public function history(): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->progressService->getHistory($userId);

            return response()->json([
                'success' => true,
                'message' => 'Historial de simulacros obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el historial: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/progress/stats
     * Estadísticas generales consolidadas del estudiante.
     */
    public function stats(): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->progressService->getGeneralStats($userId);

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas generales obtenidas correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar las estadísticas: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * GET /api/progress/subject/{subjectId}
     * Rendimiento del estudiante en una materia específica.
     */
    public function performanceBySubject($subjectId): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->progressService->getSubjectPerformance($userId, (int) $subjectId);

            return response()->json([
                'success' => true,
                'message' => 'Rendimiento por materia obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el rendimiento de la materia: ' . $e->getMessage(),
                'errors' => [],
            ], 404);
        }
    }

    /**
     * GET /api/progress/topic/{topicId}
     * Rendimiento del estudiante en un tema específico (`user_topic_performance`).
     */
    public function performanceByTopic($topicId): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $data = $this->progressService->getTopicPerformance($userId, (int) $topicId);

            return response()->json([
                'success' => true,
                'message' => 'Rendimiento por tema obtenido correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el rendimiento del tema: ' . $e->getMessage(),
                'errors' => [],
            ], 404);
        }
    }
}
