<?php

namespace App\Http\Controllers\Exam;

use App\Exceptions\InsufficientQuestionsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\FinishExamRequest;
use App\Http\Requests\Exam\StartExamRequest;
use App\Http\Requests\Exam\SubmitAnswerRequest;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExamController extends Controller
{
    protected ExamService $examService;

    public function __construct(ExamService $examService)
    {
        $this->examService = $examService;
    }

    /**
     * Obtiene el ID del usuario activo o utiliza 1 como fallback si no hay sesión iniciada.
     */
    protected function getUserId(): int
    {
        return Auth::id() ?? 1;
    }

    /**
     * POST /api/exams/configure
     * Configura y crea un nuevo simulacro o práctica con preguntas asignadas.
     */
    public function configure(StartExamRequest $request): JsonResponse
    {
        try {
            $result = $this->examService->configureExam($this->getUserId(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Simulacro configurado e iniciado correctamente.',
                'data' => $result,
            ], 201);
        } catch (InsufficientQuestionsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'available' => $e->getAvailable(),
                    'required' => $e->getRequired(),
                ],
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al configurar el simulacro: ' . $e->getMessage(),
                'errors' => [],
            ], 400);
        }
    }

    /**
     * GET /api/exams/{id}/questions
     * Obtiene las preguntas asignadas a un intento de simulacro o práctica en progreso.
     */
    public function getQuestions(int $id): JsonResponse
    {
        try {
            $data = $this->examService->getQuestions($id, $this->getUserId());

            return response()->json([
                'success' => true,
                'message' => 'Preguntas del simulacro obtenidas correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener las preguntas: ' . $e->getMessage(),
                'errors' => [],
            ], 404);
        }
    }

    /**
     * POST /api/exams/{id}/answer
     * Registra o actualiza la respuesta enviada por el estudiante.
     */
    public function submitAnswer(SubmitAnswerRequest $request, int $id): JsonResponse
    {
        try {
            $data = $this->examService->submitAnswer($id, $this->getUserId(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Respuesta registrada correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la respuesta: ' . $e->getMessage(),
                'errors' => [],
            ], 400);
        }
    }

    /**
     * POST /api/exams/{id}/finish
     * Finaliza el simulacro, califica y calcula el rendimiento.
     */
    public function finish(FinishExamRequest $request, int $id): JsonResponse
    {
        try {
            $data = $this->examService->finishExam($id, $this->getUserId());

            return response()->json([
                'success' => true,
                'message' => 'Simulacro finalizado y calificado correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar el simulacro: ' . $e->getMessage(),
                'errors' => [],
            ], 400);
        }
    }

    /**
     * GET /api/exams/{id}/results
     * Muestra los resultados detallados del simulacro completado.
     */
    public function results(int $id): JsonResponse
    {
        try {
            $data = $this->examService->getExamResults($id, $this->getUserId());

            return response()->json([
                'success' => true,
                'message' => 'Resultados del simulacro obtenidos correctamente.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron consultar los resultados: ' . $e->getMessage(),
                'errors' => [],
            ], 404);
        }
    }
}
