<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
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
     * Obtiene el ID del usuario activo o utiliza 1 como fallback si no hay sesión iniciada.
     */
    protected function getUserId(): int
    {
        return Auth::id() ?? 1;
    }

    /**
     * GET /api/progress
     * Dashboard general de progreso académico del estudiante.
     */
    public function dashboard(): JsonResponse
    {
        try {
            $data = $this->progressService->getDashboardOverview($this->getUserId());

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
        try {
            $data = $this->progressService->getHistory($this->getUserId());

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
        try {
            $data = $this->progressService->getGeneralStats($this->getUserId());

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
        try {
            $data = $this->progressService->getSubjectPerformance($this->getUserId(), (int) $subjectId);

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
        try {
            $data = $this->progressService->getTopicPerformance($this->getUserId(), (int) $topicId);

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
