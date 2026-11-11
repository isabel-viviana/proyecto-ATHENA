<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Services\StudentTokenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StudentExamController extends Controller
{
    /**
     * Resuelve de forma segura la identidad del estudiante a partir del token Bearer.
     */
    protected function getAuthenticatedStudent(Request $request): ?array
    {
        $token = StudentTokenService::extractTokenFromRequest($request);
        return StudentTokenService::validateToken($token);
    }

    /**
     * Devuelve las opciones de configuración disponibles para simulacros.
     * Solo permite seleccionar áreas con reactivos reales publicados.
     *
     * GET /api/v1/student/exams/config-options
     */
    public function configOptions(Request $request): JsonResponse
    {
        $subjects = Subject::orderBy('id', 'asc')->get();
        $counts = Question::where('status', 'published')
            ->select('subject_id', DB::raw('count(*) as count'))
            ->groupBy('subject_id')
            ->pluck('count', 'subject_id')
            ->all();

        $areas = $subjects->map(function ($s) use ($counts) {
            $pubCount = $counts[$s->id] ?? 0;
            return [
                'id'              => $s->id,
                'name'            => $s->name,
                'code'            => $s->code,
                'published_count' => $pubCount,
                'available'       => $pubCount > 0,
                'status'          => $pubCount > 0 ? 'Activo' : 'Próximamente',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'notice'          => 'Simulacro Parcial — Lectura Crítica Saber 11 (49 preguntas oficiales publicadas)',
                'areas'           => $areas,
                'question_counts' => [
                    ['count' => 10, 'label' => '10 preguntas', 'recommended_minutes' => 20],
                    ['count' => 20, 'label' => '20 preguntas', 'recommended_minutes' => 40],
                    ['count' => 25, 'label' => '25 preguntas', 'recommended_minutes' => 50],
                    ['count' => 49, 'label' => '49 preguntas (Cuadernillo Completo)', 'recommended_minutes' => 98],
                ],
                'modes' => [
                    ['id' => 'area', 'label' => 'Simulacro por Área', 'description' => 'Evaluación focalizada en un área oficial del ICFES'],
                    ['id' => 'custom', 'label' => 'Simulacro Personalizado', 'description' => 'Ajusta cantidad de reactivos y tiempo disponible'],
                ],
            ],
        ]);
    }

    /**
     * Inicia un nuevo intento de simulacro para el estudiante autenticado.
     * Selecciona preguntas publicadas en backend y protege las claves correctas.
     *
     * POST /api/v1/student/exams/start
     */
    public function start(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada. Debes iniciar sesión.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        $validated = $request->validate([
            'subject_id'       => 'required|integer',
            'mode'             => 'nullable|string|in:area,custom,full',
            'questions_count'  => 'required|integer|in:10,20,25,49',
            'duration_minutes' => 'nullable|integer|min:5|max:300',
        ]);

        $subjectId = (int) $validated['subject_id'];

        // 1. Validar que la materia seleccionada tenga reactivos publicados
        $availableCount = Question::where('status', 'published')
            ->where('subject_id', $subjectId)
            ->count();

        if ($availableCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'El área seleccionada aún no tiene preguntas publicadas en el banco oficial.',
            ], 422);
        }

        $subject = Subject::find($subjectId);
        $subjectName = $subject ? $subject->name : 'Lectura Crítica';

        // 2. Selección de preguntas en backend sin duplicados
        $requestedCount = min((int) $validated['questions_count'], $availableCount);

        $questionsQuery = Question::where('status', 'published')
            ->where('subject_id', $subjectId);

        if ($requestedCount >= $availableCount) {
            // Cuadernillo completo: conservar orden oficial (1 -> 49)
            $selectedQuestions = $questionsQuery
                ->orderBy('order_in_group', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            // Muestra aleatoria sin repetición
            $selectedQuestions = $questionsQuery
                ->inRandomOrder()
                ->limit($requestedCount)
                ->get()
                ->sortBy('order_in_group')
                ->values();
        }

        if ($selectedQuestions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No fue posible recuperar reactivos suficientes para el simulacro.',
            ], 422);
        }

        $selectedQuestions->load(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic']);

        // 3. Duración recomendada (~2 minutos por reactivo)
        $durationMinutes = (int) ($validated['duration_minutes'] ?? ($requestedCount * 2));
        $now = now();
        $expiresAt = (clone $now)->addMinutes($durationMinutes);

        // 4. Crear registro del intento en Supabase PostgreSQL
        $attemptId = (string) Str::uuid();
        $questionIds = $selectedQuestions->pluck('id')->values()->all();

        $attempt = ExamAttempt::create([
            'id'                   => $attemptId,
            'student_id'           => $studentId,
            'subject_id'           => $subjectId,
            'title'                => "Simulacro Parcial — {$subjectName} Saber 11",
            'mode'                 => $validated['mode'] ?? 'area',
            'questions_count'      => count($questionIds),
            'duration_minutes'     => $durationMinutes,
            'started_at'           => $now,
            'expires_at'           => $expiresAt,
            'status'               => 'in_progress',
            'score'                => 0,
            'correct_answers'      => 0,
            'incorrect_answers'    => 0,
            'omitted_answers'      => count($questionIds),
            'accuracy'             => 0.00,
            'total_time_seconds'   => 0,
            'question_ids'         => $questionIds,
            'flagged_question_ids' => [],
        ]);

        // 5. Pre-crear registros de respuesta en blanco
        foreach ($questionIds as $idx => $qId) {
            ExamAttemptAnswer::create([
                'id'                     => (string) Str::uuid(),
                'attempt_id'             => $attemptId,
                'question_id'            => $qId,
                'order_position'         => $idx + 1,
                'selected_option_id'     => null,
                'selected_option_letter' => null,
                'is_correct'             => null,
                'time_spent_seconds'     => 0,
                'flagged'                => false,
            ]);
        }

        // 6. Cargar preguntas formateadas de forma segura (sin is_correct)
        $formattedQuestions = $this->formatQuestionsForExam($selectedQuestions);

        return response()->json([
            'success' => true,
            'message' => 'Simulacro iniciado exitosamente.',
            'data'    => [
                'attempt' => [
                    'id'                => $attempt->id,
                    'title'             => $attempt->title,
                    'subject_name'      => $subjectName,
                    'mode'              => $attempt->mode,
                    'questions_count'   => $attempt->questions_count,
                    'duration_minutes'  => $attempt->duration_minutes,
                    'started_at'        => $attempt->started_at->toIso8601String(),
                    'expires_at'        => $attempt->expires_at->toIso8601String(),
                    'remaining_seconds' => max(0, $expiresAt->diffInSeconds($now)),
                    'status'            => $attempt->status,
                ],
                'questions' => $formattedQuestions,
            ],
        ], 201);
    }

    /**
     * Consulta si el estudiante tiene un simulacro en progreso activo.
     * Permite la recuperación inmediata de sesión si recarga o cierra la ventana.
     *
     * GET /api/v1/student/exams/active
     */
    public function active(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $studentId = (string) $authStudent['sub'];

        $attempt = ExamAttempt::where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->orderBy('started_at', 'desc')
            ->first();

        if (! $attempt) {
            return response()->json(['success' => true, 'data' => null]);
        }

        // Si el tiempo ya expiró en el servidor, auto-finalizar
        if (now()->greaterThanOrEqualTo($attempt->expires_at)) {
            $this->finalizeAttempt($attempt);
            return response()->json(['success' => true, 'data' => null]);
        }

        return $this->show($request, $attempt->id);
    }

    /**
     * Consulta el estado y las preguntas de un intento de simulacro.
     *
     * GET /api/v1/student/exams/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        $attempt = ExamAttempt::with('subject')->find($id);

        if (! $attempt || $attempt->student_id !== $studentId) {
            return response()->json([
                'success' => false,
                'message' => 'El simulacro solicitado no existe o no pertenece a tu cuenta.',
            ], 404);
        }

        // Si expiró el tiempo pero sigue in_progress, auto-finalizar
        if ($attempt->status === 'in_progress' && now()->greaterThanOrEqualTo($attempt->expires_at)) {
            $attempt = $this->finalizeAttempt($attempt);
        }

        // Cargar respuestas registradas hasta el momento
        $answers = ExamAttemptAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');

        // Cargar preguntas en el orden exacto del intento
        $orderedQuestionIds = $attempt->question_ids ?: [];
        $questions = Question::whereIn('id', $orderedQuestionIds)
            ->with(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic'])
            ->get()
            ->sortBy(function ($q) use ($orderedQuestionIds) {
                return array_search($q->id, $orderedQuestionIds);
            })
            ->values();

        $isInProgress = ($attempt->status === 'in_progress');
        $formattedQuestions = $this->formatQuestionsForExam($questions, $isInProgress);

        $answersMap = [];
        foreach ($answers as $qId => $ans) {
            $answersMap[$qId] = [
                'selected_option_id'     => $ans->selected_option_id,
                'selected_option_letter' => $ans->selected_option_letter,
                'flagged'                => (bool) $ans->flagged,
                'time_spent_seconds'     => $ans->time_spent_seconds,
            ];
        }

        $remainingSeconds = $isInProgress
            ? max(0, (int) ($attempt->expires_at->timestamp - now()->timestamp))
            : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'attempt' => [
                    'id'                   => $attempt->id,
                    'title'                => $attempt->title,
                    'subject_name'         => $attempt->subject ? $attempt->subject->name : 'Lectura Crítica',
                    'mode'                 => $attempt->mode,
                    'questions_count'      => $attempt->questions_count,
                    'duration_minutes'     => $attempt->duration_minutes,
                    'started_at'           => $attempt->started_at->toIso8601String(),
                    'expires_at'           => $attempt->expires_at->toIso8601String(),
                    'finished_at'          => $attempt->finished_at ? $attempt->finished_at->toIso8601String() : null,
                    'remaining_seconds'    => $remainingSeconds,
                    'status'               => $attempt->status,
                    'flagged_question_ids' => $attempt->flagged_question_ids ?: [],
                ],
                'questions'   => $formattedQuestions,
                'answers_map' => $answersMap,
            ],
        ]);
    }

    /**
     * Guarda o actualiza la respuesta de una pregunta en el simulacro activo.
     * Permite cambiar de opción antes de finalizar y marcar para revisión.
     *
     * POST /api/v1/student/exams/{id}/answers
     */
    public function saveAnswer(Request $request, string $id): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        $attempt = ExamAttempt::find($id);

        if (! $attempt || $attempt->student_id !== $studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Simulacro no encontrado.',
            ], 404);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden registrar respuestas: el simulacro ya ha finalizado.',
            ], 403);
        }

        // Validar expiración en el servidor
        if (now()->greaterThanOrEqualTo($attempt->expires_at)) {
            $this->finalizeAttempt($attempt);
            return response()->json([
                'success' => false,
                'message' => 'El tiempo del simulacro ha finalizado automáticamente.',
            ], 403);
        }

        $validated = $request->validate([
            'question_id'            => 'required|uuid',
            'selected_option_id'     => 'nullable|uuid',
            'selected_option_letter' => 'nullable|string|in:A,B,C,D,a,b,c,d',
            'time_spent_seconds'     => 'nullable|integer|min:0',
            'flagged'                => 'nullable|boolean',
        ]);

        $questionId = $validated['question_id'];

        if (! in_array($questionId, $attempt->question_ids ?: [])) {
            return response()->json([
                'success' => false,
                'message' => 'La pregunta no pertenece a este simulacro.',
            ], 422);
        }

        // Resolver letra y opción
        $selectedOptionId = $validated['selected_option_id'] ?? null;
        $selectedLetter = !empty($validated['selected_option_letter'])
            ? strtoupper(trim($validated['selected_option_letter']))
            : null;

        if ($selectedOptionId && ! $selectedLetter) {
            $opt = QuestionOption::find($selectedOptionId);
            if ($opt) $selectedLetter = $opt->option_letter;
        } elseif ($selectedLetter && ! $selectedOptionId) {
            $opt = QuestionOption::where('question_id', $questionId)
                ->where('option_letter', $selectedLetter)
                ->first();
            if ($opt) $selectedOptionId = $opt->id;
        }

        $flagged = (bool) ($validated['flagged'] ?? false);
        $timeSpent = (int) ($validated['time_spent_seconds'] ?? 0);

        // Actualizar o crear registro en exam_attempt_answers
        ExamAttemptAnswer::updateOrCreate(
            [
                'attempt_id'  => $attempt->id,
                'question_id' => $questionId,
            ],
            [
                'selected_option_id'     => $selectedOptionId,
                'selected_option_letter' => $selectedLetter,
                'time_spent_seconds'     => $timeSpent,
                'flagged'                => $flagged,
                'updated_at'             => now(),
            ]
        );

        // Actualizar lista de preguntas marcadas en el intento
        $flaggedList = collect($attempt->flagged_question_ids ?: []);
        if ($flagged && ! $flaggedList->contains($questionId)) {
            $flaggedList->push($questionId);
        } elseif (! $flagged && $flaggedList->contains($questionId)) {
            $flaggedList = $flaggedList->reject(fn($q) => $q === $questionId);
        }

        $attempt->update([
            'flagged_question_ids' => $flaggedList->values()->all(),
            'updated_at'           => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Respuesta guardada correctamente.',
            'data'    => [
                'question_id'            => $questionId,
                'selected_option_id'     => $selectedOptionId,
                'selected_option_letter' => $selectedLetter,
                'flagged'                => $flagged,
            ],
        ]);
    }

    /**
     * Finaliza y califica el simulacro 100% en el backend Laravel.
     * Bloquea nuevos intentos y calcula la tarjeta de resultados oficial.
     *
     * POST /api/v1/student/exams/{id}/finish
     */
    public function finish(Request $request, string $id): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        $attempt = ExamAttempt::find($id);

        if (! $attempt || $attempt->student_id !== $studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Simulacro no encontrado.',
            ], 404);
        }

        // Si ya está completado, no volver a calificar (evitar duplicaciones)
        if ($attempt->status === 'completed') {
            return $this->results($request, $id);
        }

        $completedAttempt = $this->finalizeAttempt($attempt);

        return $this->results($request, $completedAttempt->id);
    }

    /**
     * Muestra la tarjeta de resultados y revisión pregunta por pregunta.
     *
     * GET /api/v1/student/exams/{id}/results
     */
    public function results(Request $request, string $id): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        $attempt = ExamAttempt::with('subject')->find($id);

        if (! $attempt || $attempt->student_id !== $studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Simulacro no encontrado.',
            ], 404);
        }

        // Cargar respuestas del intento
        $attemptAnswers = ExamAttemptAnswer::where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');

        // Cargar preguntas en orden
        $orderedQuestionIds = $attempt->question_ids ?: [];
        $questions = Question::whereIn('id', $orderedQuestionIds)
            ->with(['stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'options', 'subject', 'topic'])
            ->get()
            ->sortBy(function ($q) use ($orderedQuestionIds) {
                return array_search($q->id, $orderedQuestionIds);
            })
            ->values();

        // Formatear revisión pregunta por pregunta
        $questionsDetail = $questions->map(function ($q, $index) use ($attemptAnswers) {
            $ans = $attemptAnswers->get($q->id);
            $correctOption = $q->options->firstWhere('is_correct', true);

            $selectedOptionId = $ans ? $ans->selected_option_id : null;
            $selectedLetter = $ans ? $ans->selected_option_letter : null;
            $isCorrect = $ans ? (bool) $ans->is_correct : false;
            $isOmitted = empty($selectedOptionId) && empty($selectedLetter);

            $explanation = !empty(trim($q->explanation))
                ? $q->explanation
                : 'Explicación pedagógica en proceso de calibración docente por el equipo académico ICFES.';

            return [
                'order'                  => $index + 1,
                'question_id'            => $q->id,
                'statement'              => $q->statement,
                'subject_name'           => $q->subject ? $q->subject->name : 'Lectura Crítica',
                'topic_name'             => $q->topic ? $q->topic->name : null,
                'stimulus'               => $q->stimulus ? [
                    'id'                    => $q->stimulus->id,
                    'title'                 => $q->stimulus->title,
                    'content_text'          => $q->stimulus->content_text,
                    'instructions'          => $q->stimulus->instructions,
                    'source_page'           => $q->stimulus->source_page,
                    'confirmed_media_assets'=> $q->stimulus->confirmedMediaAssets,
                    'visual_transcription'  => $q->stimulus->visual_transcription,
                ] : null,
                'confirmed_media_assets' => $q->confirmedMediaAssets,
                'options'                => $q->options->map(fn($o) => [
                    'id'            => $o->id,
                    'option_letter' => $o->option_letter,
                    'option_text'   => $o->option_text,
                    'is_correct'    => (bool) $o->is_correct,
                ]),
                'user_selected_option'   => $selectedLetter,
                'user_selected_id'       => $selectedOptionId,
                'correct_option_letter'  => $correctOption ? $correctOption->option_letter : null,
                'correct_option_id'      => $correctOption ? $correctOption->id : null,
                'is_correct'             => $isCorrect,
                'is_omitted'             => $isOmitted,
                'flagged'                => $ans ? (bool) $ans->flagged : false,
                'time_spent_seconds'     => $ans ? (int) $ans->time_spent_seconds : 0,
                'explanation'            => $explanation,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'attempt' => [
                    'id'                 => $attempt->id,
                    'title'              => $attempt->title,
                    'subject_name'       => $attempt->subject ? $attempt->subject->name : 'Lectura Crítica',
                    'mode'               => $attempt->mode,
                    'questions_count'    => $attempt->questions_count,
                    'duration_minutes'   => $attempt->duration_minutes,
                    'started_at'         => $attempt->started_at->toIso8601String(),
                    'finished_at'        => $attempt->finished_at ? $attempt->finished_at->toIso8601String() : null,
                    'status'             => $attempt->status,
                    'score'              => $attempt->score,
                    'correct_answers'    => $attempt->correct_answers,
                    'incorrect_answers'  => $attempt->incorrect_answers,
                    'omitted_answers'    => $attempt->omitted_answers,
                    'accuracy'           => (float) $attempt->accuracy,
                    'total_time_seconds' => $attempt->total_time_seconds,
                ],
                'questions_detail' => $questionsDetail,
            ],
        ]);
    }

    /**
     * Consulta el historial de simulacros finalizados del estudiante autenticado.
     *
     * GET /api/v1/student/exams/history
     */
    public function history(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        $attempts = ExamAttempt::with('subject')
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->orderBy('finished_at', 'desc')
            ->get();

        $data = $attempts->map(function ($a) {
            return [
                'id'                 => $a->id,
                'title'              => $a->title,
                'subject_name'       => $a->subject ? $a->subject->name : 'Lectura Crítica',
                'mode'               => $a->mode,
                'questions_count'    => $a->questions_count,
                'score'              => $a->score,
                'correct_answers'    => $a->correct_answers,
                'incorrect_answers'  => $a->incorrect_answers,
                'omitted_answers'    => $a->omitted_answers,
                'accuracy'           => (float) $a->accuracy,
                'total_time_seconds' => $a->total_time_seconds,
                'started_at'         => $a->started_at->toIso8601String(),
                'finished_at'        => $a->finished_at ? $a->finished_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Método auxiliar privado: califica y finaliza un intento de simulacro.
     */
    protected function finalizeAttempt(ExamAttempt $attempt): ExamAttempt
    {
        $orderedQuestionIds = $attempt->question_ids ?: [];
        $questions = Question::whereIn('id', $orderedQuestionIds)->with('options')->get();
        $answers = ExamAttemptAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');

        $correctCount = 0;
        $incorrectCount = 0;
        $omittedCount = 0;
        $totalTime = 0;

        foreach ($questions as $q) {
            $correctOption = $q->options->firstWhere('is_correct', true);
            $ans = $answers->get($q->id);

            $selectedOptionId = $ans ? $ans->selected_option_id : null;
            $selectedLetter = $ans ? $ans->selected_option_letter : null;
            $timeSpent = $ans ? $ans->time_spent_seconds : 0;
            $totalTime += $timeSpent;

            $isCorrect = false;

            if (empty($selectedOptionId) && empty($selectedLetter)) {
                $omittedCount++;
                $isCorrect = false;
            } else {
                if ($correctOption && ($selectedOptionId === $correctOption->id || $selectedLetter === $correctOption->option_letter)) {
                    $isCorrect = true;
                    $correctCount++;
                } else {
                    $isCorrect = false;
                    $incorrectCount++;
                }
            }

            // Actualizar registro de respuesta individual
            if ($ans) {
                $ans->update([
                    'is_correct' => $isCorrect,
                    'updated_at' => now(),
                ]);
            }

            // Sincronizar en student_answers para reflejar en el perfil/estadísticas sin duplicar
            if (!empty($selectedOptionId)) {
                StudentAnswer::updateOrCreate(
                    [
                        'student_id'  => $attempt->student_id,
                        'question_id' => $q->id,
                    ],
                    [
                        'selected_option_id'     => $selectedOptionId,
                        'selected_option_letter' => $selectedLetter,
                        'is_correct'             => $isCorrect,
                        'practice_mode'          => 'simulacro',
                        'time_spent_seconds'     => $timeSpent,
                        'answered_at'            => now(),
                    ]
                );
            }
        }

        $totalQuestions = count($orderedQuestionIds);
        $accuracy = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 1) : 0.00;
        $score = $correctCount * 10;
        $now = now();

        $attempt->update([
            'status'             => 'completed',
            'finished_at'        => $now,
            'score'              => $score,
            'correct_answers'    => $correctCount,
            'incorrect_answers'  => $incorrectCount,
            'omitted_answers'    => $omittedCount,
            'accuracy'           => $accuracy,
            'total_time_seconds' => min($attempt->duration_minutes * 60, max($totalTime, $now->diffInSeconds($attempt->started_at))),
            'updated_at'         => $now,
        ]);

        return $attempt->fresh();
    }

    /**
     * Formatea las preguntas protegiendo 'is_correct' y 'explanation' si el examen está en curso.
     */
    protected function formatQuestionsForExam($questions, bool $hideKeys = true): array
    {
        return $questions->map(function ($q, $idx) use ($hideKeys) {
            $rawOptions = $q->relationLoaded('options')
                ? $q->options->sortBy('option_letter')
                : QuestionOption::where('question_id', $q->id)->orderBy('option_letter', 'asc')->get();

            $options = $rawOptions->map(function ($opt) use ($hideKeys) {
                    $item = [
                        'id'                => $opt->id,
                        'option_letter'     => $opt->option_letter,
                        'option_text'       => $opt->option_text,
                        'option_image_path' => $opt->option_image_path,
                    ];
                    if (! $hideKeys) {
                        $item['is_correct'] = (bool) $opt->is_correct;
                    }
                    return $item;
                });

            $data = [
                'id'                     => $q->id,
                'order'                  => $idx + 1,
                'statement'              => $q->statement,
                'question_image_path'    => $q->question_image_path,
                'options'                => $options,
                'confirmed_media_assets' => $q->confirmedMediaAssets,
                'stimulus'               => $q->stimulus ? [
                    'id'                    => $q->stimulus->id,
                    'title'                 => $q->stimulus->title,
                    'content_text'          => $q->stimulus->content_text,
                    'instructions'          => $q->stimulus->instructions,
                    'source_page'           => $q->stimulus->source_page,
                    'confirmed_media_assets'=> $q->stimulus->confirmedMediaAssets,
                    'visual_transcription'  => $q->stimulus->visual_transcription,
                ] : null,
                'subject'                => $q->subject ? [
                    'id'   => $q->subject->id,
                    'name' => $q->subject->name,
                ] : null,
                'topic'                  => $q->topic ? [
                    'id'   => $q->topic->id,
                    'name' => $q->topic->name,
                ] : null,
            ];

            if (! $hideKeys) {
                $data['explanation'] = $q->explanation;
            }

            return $data;
        })->values()->all();
    }
}

