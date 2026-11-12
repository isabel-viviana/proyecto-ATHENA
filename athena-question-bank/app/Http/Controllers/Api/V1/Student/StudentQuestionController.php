<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Services\StudentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentQuestionController extends Controller
{
    /**
     * Resuelve de forma segura la identidad del estudiante a partir del token Bearer.
     * Retorna el payload verificado o null si no se proporcionó o no es válido.
     */
    protected function getAuthenticatedStudent(Request $request): ?array
    {
        $token = StudentTokenService::extractTokenFromRequest($request);
        return StudentTokenService::validateToken($token);
    }

    /**
     * Consulta las preguntas disponibles para práctica de estudiantes.
     * Solo retorna preguntas en estado 'published'.
     * Protege rigurosamente las respuestas: NUNCA expone 'is_correct' en el listado.
     *
     * GET /api/v1/student/questions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Question::query()
            ->where('status', 'published')
            ->with([
                'stimulus.confirmedMediaAssets',
                'confirmedMediaAssets',
                'subject',
                'topic',
            ]);

        // 1. Filtro por Materia (acepta ID numérico o código/nombre)
        $subjectInput = $request->input('subject_id') ?: $request->input('subject');
        if ($subjectInput && $subjectInput !== 'Todas' && $subjectInput !== 'all') {
            if (is_numeric($subjectInput)) {
                $query->where('subject_id', (int) $subjectInput);
            } else {
                $nameMap = [
                    'Lectura Crítica' => 1,
                    'lectura-critica' => 1,
                    'Matemáticas' => 2,
                    'matematicas' => 2,
                    'Ciencias Naturales' => 3,
                    'ciencias-naturales' => 3,
                    'Sociales y Ciudadanas' => 4,
                    'sociales-ciudadanas' => 4,
                    'Inglés' => 5,
                    'ingles' => 5,
                ];
                if (isset($nameMap[$subjectInput])) {
                    $query->where('subject_id', $nameMap[$subjectInput]);
                }
            }
        }

        // 2. Búsqueda en enunciado (ILIKE en PostgreSQL)
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('statement', 'ILIKE', "%{$search}%");
        }

        // 3. Ordenamiento: por defecto conserva el orden oficial del cuadernillo (1 -> N)
        $query->orderBy('order_in_group', 'asc')->orderBy('created_at', 'asc');

        // 4. Paginación
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $page = max(1, (int) $request->input('page', 1));
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // 5. Cargar opciones protegiendo la clave (excluyendo is_correct)
        $questionIds = collect($paginated->items())->pluck('id')->all();
        $optionsByQuestion = QuestionOption::whereIn('question_id', $questionIds)
            ->orderBy('option_letter', 'asc')
            ->get(['id', 'question_id', 'option_letter', 'option_text', 'option_image_path'])
            ->groupBy('question_id');

        // Identificar al estudiante autenticado para cargar sus respuestas previas
        $authStudent = $this->getAuthenticatedStudent($request);
        $studentId = $authStudent ? (string) $authStudent['sub'] : null;

        $studentAnswers = [];
        if ($studentId) {
            $studentAnswers = StudentAnswer::where('student_id', $studentId)
                ->whereIn('question_id', $questionIds)
                ->get()
                ->keyBy('question_id');
        }

        $items = collect($paginated->items())->map(function ($q) use ($optionsByQuestion, $studentAnswers) {
            $data = $q->toArray();
            // Inyectar opciones sin 'is_correct'
            $data['options'] = ($optionsByQuestion->get($q->id) ?? collect())->values()->all();

            // Información sobre si el estudiante ya la respondió
            if (isset($studentAnswers[$q->id])) {
                $prev = $studentAnswers[$q->id];
                $data['student_progress'] = [
                    'answered'                => true,
                    'selected_option_id'      => $prev->selected_option_id,
                    'selected_option_letter'  => $prev->selected_option_letter,
                    'is_correct'              => $prev->is_correct,
                    'answered_at'             => $prev->answered_at,
                ];
            } else {
                $data['student_progress'] = [
                    'answered' => false,
                ];
            }

            // Ocultar explicaciones completas en el listado previo para evitar filtraciones
            unset($data['explanation']);

            return $data;
        });

        // 6. Conteo por materias disponibles en el banco publicado
        $subjectCounts = Question::where('status', 'published')
            ->select('subject_id', DB::raw('count(*) as count'))
            ->groupBy('subject_id')
            ->pluck('count', 'subject_id')
            ->all();

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'available_by_subject' => $subjectCounts,
        ]);
    }

    /**
     * Consulta una pregunta publicada específica para resolver.
     * Solo permite consultar preguntas con status = 'published'.
     *
     * GET /api/v1/student/questions/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $question = Question::with([
            'stimulus.confirmedMediaAssets',
            'confirmedMediaAssets',
            'subject',
            'topic',
        ])->find($id);

        if (! $question || $question->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'La pregunta solicitada no está disponible para estudiantes.',
            ], 404);
        }

        // Cargar opciones seguras (sin is_correct)
        $options = QuestionOption::where('question_id', $question->id)
            ->orderBy('option_letter', 'asc')
            ->get(['id', 'question_id', 'option_letter', 'option_text', 'option_image_path']);

        $data = $question->toArray();
        $data['options'] = $options;
        unset($data['explanation']); // Ocultar hasta responder

        // Comprobar si ya fue respondida por el estudiante autenticado
        $authStudent = $this->getAuthenticatedStudent($request);
        $studentId = $authStudent ? (string) $authStudent['sub'] : null;

        if ($studentId) {
            $prev = StudentAnswer::where('student_id', $studentId)
                ->where('question_id', $question->id)
                ->first();
            if ($prev) {
                $data['student_progress'] = [
                    'answered'               => true,
                    'selected_option_id'     => $prev->selected_option_id,
                    'selected_option_letter' => $prev->selected_option_letter,
                    'is_correct'             => $prev->is_correct,
                    'answered_at'            => $prev->answered_at,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Valida y califica la respuesta enviada por un estudiante.
     * La validación se ejecuta 100% en el backend Laravel.
     * Registra la respuesta y devuelve retroalimentación inmediata con la clave oficial.
     *
     * SEGURIDAD ESTRICTA:
     * - El student_id se deriva exclusivamente del token JWT validado en cabecera.
     * - Cualquier student_id enviado en el cuerpo de la petición es descartado para prevenir suplantación.
     *
     * POST /api/v1/student/questions/{id}/answer
     */
    public function submitAnswer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'selected_option_id'     => 'nullable|uuid',
            'selected_option_letter' => 'nullable|string|in:A,B,C,D,a,b,c,d',
            'practice_mode'          => 'nullable|string|max:50',
            'time_spent_seconds'     => 'nullable|integer|min:0',
        ]);

        $question = Question::with('options')->find($id);

        if (! $question || $question->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'No es posible responder: la pregunta no está activa en el banco publicado.',
            ], 404);
        }

        // Determinar la opción seleccionada por ID o por letra
        $options = $question->options;
        $selectedOption = null;

        if (!empty($validated['selected_option_id'])) {
            $selectedOption = $options->firstWhere('id', $validated['selected_option_id']);
        } elseif (!empty($validated['selected_option_letter'])) {
            $letter = strtoupper(trim($validated['selected_option_letter']));
            $selectedOption = $options->firstWhere('option_letter', $letter);
        }

        if (! $selectedOption) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar una opción de respuesta válida (A, B, C o D).',
            ], 422);
        }

        // Buscar la opción correcta oficial
        $correctOption = $options->firstWhere('is_correct', true);
        $isCorrect = (bool) $selectedOption->is_correct;

        // Autenticación segura y prevención de suplantación (Anti-Spoofing):
        $authStudent = $this->getAuthenticatedStudent($request);
        $studentId = $authStudent ? (string) $authStudent['sub'] : null;

        $practiceMode = $validated['practice_mode'] ?? 'practice';
        $timeSpent = (int) ($validated['time_spent_seconds'] ?? 0);

        // Registrar la respuesta en PostgreSQL
        $answerRecord = StudentAnswer::create([
            'id'                     => (string) Str::uuid(),
            'student_id'             => $studentId,
            'question_id'            => $question->id,
            'selected_option_id'     => $selectedOption->id,
            'selected_option_letter' => $selectedOption->option_letter,
            'is_correct'             => $isCorrect,
            'practice_mode'          => $practiceMode,
            'time_spent_seconds'     => $timeSpent,
            'answered_at'            => now(),
        ]);

        // Mensaje de explicación pedagógica
        $explanation = !empty(trim($question->explanation))
            ? $question->explanation
            : 'Explicación pedagógica en proceso de calibración docente por el equipo académico ICFES.';

        return response()->json([
            'success' => true,
            'data'    => [
                'answer_id'              => $answerRecord->id,
                'student_id'             => $answerRecord->student_id,
                'question_id'            => $question->id,
                'is_correct'             => $isCorrect,
                'selected_option_id'     => $selectedOption->id,
                'selected_option_letter' => $selectedOption->option_letter,
                'correct_option_id'      => $correctOption ? $correctOption->id : null,
                'correct_option_letter'  => $correctOption ? $correctOption->option_letter : null,
                'explanation'            => $explanation,
                'answered_at'            => $answerRecord->answered_at->toIso8601String(),
            ],
            'message' => $isCorrect ? '¡Respuesta correcta!' : 'Respuesta incorrecta.',
        ]);
    }

    /**
     * Devuelve el catálogo de materias disponibles con el conteo de preguntas publicadas.
     *
     * GET /api/v1/student/subjects
     */
    public function subjects(): JsonResponse
    {
        $subjects = Subject::orderBy('id', 'asc')->get();
        $counts = Question::where('status', 'published')
            ->select('subject_id', DB::raw('count(*) as count'))
            ->groupBy('subject_id')
            ->pluck('count', 'subject_id')
            ->all();

        $data = $subjects->map(function ($s) use ($counts) {
            return [
                'id'              => $s->id,
                'name'            => $s->name,
                'code'            => $s->code,
                'published_count' => $counts[$s->id] ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Consulta el resumen de progreso y desempeño del estudiante autenticado
     * calculado en tiempo real a partir de student_answers en PostgreSQL.
     * Soporta filtros por período (7d, 30d, all) y análisis pedagógico por área ICFES.
     *
     * GET /api/v1/student/progress
     */
    public function progress(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        $studentId = $authStudent ? (string) $authStudent['sub'] : $request->input('student_id', '1');

        $period = $request->input('period', 'all'); // '7d', '30d', 'all'

        $answersQuery = StudentAnswer::where('student_id', $studentId);
        if ($period === '7d') {
            $answersQuery->where('answered_at', '>=', now()->subDays(7));
        } elseif ($period === '30d') {
            $answersQuery->where('answered_at', '>=', now()->subDays(30));
        }

        $totalAnswered = (clone $answersQuery)->count();
        $correctCount = (clone $answersQuery)->where('is_correct', true)->count();
        $incorrectCount = $totalAnswered - $correctCount;
        $accuracy = $totalAnswered > 0 ? round(($correctCount / $totalAnswered) * 100, 1) : 0;
        $totalTimeSeconds = (clone $answersQuery)->sum('time_spent_seconds');

        // Cálculo de Racha Real de Días de Estudio (días consecutivos con actividad)
        $dates = StudentAnswer::where('student_id', $studentId)
            ->select(DB::raw('DATE(answered_at) as practice_date'))
            ->groupBy(DB::raw('DATE(answered_at)'))
            ->orderBy('practice_date', 'desc')
            ->pluck('practice_date')
            ->map(fn($d) => substr((string) $d, 0, 10))
            ->all();

        $realStreak = 0;
        $currentDate = now()->toDateString();
        $yesterdayDate = now()->subDay()->toDateString();

        if (!empty($dates)) {
            if ($dates[0] === $currentDate || $dates[0] === $yesterdayDate) {
                $checkDate = \Carbon\Carbon::parse($dates[0]);
                $realStreak = 1;
                for ($i = 1; $i < count($dates); $i++) {
                    $prevDate = \Carbon\Carbon::parse($dates[$i]);
                    if ($checkDate->diffInDays($prevDate) === 1) {
                        $realStreak++;
                        $checkDate = $prevDate;
                    } else {
                        break;
                    }
                }
            }
        }

        // Evolución temporal y actividad diaria
        $dailyActivity = (clone $answersQuery)
            ->select(
                DB::raw('DATE(answered_at) as date'),
                DB::raw('count(id) as total'),
                DB::raw('sum(case when is_correct = true then 1 else 0 end) as correct'),
                DB::raw('sum(case when is_correct = false then 1 else 0 end) as incorrect'),
                DB::raw('round(avg(time_spent_seconds), 1) as avg_time')
            )
            ->groupBy(DB::raw('DATE(answered_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                $tot = (int) $row->total;
                $corr = (int) $row->correct;
                return [
                    'date'             => (string) $row->date,
                    'total'            => $tot,
                    'correct'          => $corr,
                    'incorrect'        => (int) $row->incorrect,
                    'accuracy'         => $tot > 0 ? round(($corr / $tot) * 100, 1) : 0,
                    'avg_time_seconds' => (float) $row->avg_time,
                ];
            });

        // Desglose oficial de las 5 Áreas del ICFES
        $allSubjects = Subject::orderBy('id', 'asc')->get();
        $subjectCounts = Question::where('status', 'published')
            ->select('subject_id', DB::raw('count(*) as count'))
            ->groupBy('subject_id')
            ->pluck('count', 'subject_id')
            ->all();

        $answeredBySubjectRaw = DB::connection('question_bank')
            ->table('student_answers as sa')
            ->join('questions as q', 'sa.question_id', '=', 'q.id')
            ->where('sa.student_id', $studentId)
            ->when($period === '7d', fn($q) => $q->where('sa.answered_at', '>=', now()->subDays(7)))
            ->when($period === '30d', fn($q) => $q->where('sa.answered_at', '>=', now()->subDays(30)))
            ->select(
                'q.subject_id',
                DB::raw('count(sa.id) as total_answered'),
                DB::raw('sum(case when sa.is_correct = true then 1 else 0 end) as correct_count'),
                DB::raw('round(avg(sa.time_spent_seconds), 1) as avg_time_seconds')
            )
            ->groupBy('q.subject_id')
            ->get()
            ->keyBy('subject_id');

        $bySubject = $allSubjects->map(function ($s) use ($answeredBySubjectRaw, $subjectCounts) {
            $raw = $answeredBySubjectRaw->get($s->id);
            $total = $raw ? (int) $raw->total_answered : 0;
            $correct = $raw ? (int) $raw->correct_count : 0;
            $publishedInBank = $subjectCounts[$s->id] ?? 0;

            return [
                'subject_id'       => $s->id,
                'subject_name'     => $s->name,
                'subject_code'     => $s->code,
                'published_in_bank'=> $publishedInBank,
                'status'           => $publishedInBank > 0 ? 'Activo' : 'Próximamente',
                'total_answered'   => $total,
                'correct_count'    => $correct,
                'incorrect_count'  => $total - $correct,
                'accuracy'         => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
                'avg_time_seconds' => $raw ? (float) $raw->avg_time_seconds : 0.0,
            ];
        });

        // Análisis Académico basado estrictamente en datos reales
        $activePracticed = $bySubject->filter(fn($s) => $s['total_answered'] > 0);
        $strongestArea = $activePracticed->sortByDesc('accuracy')->first();
        $weakestArea = $activePracticed->sortBy('accuracy')->first();

        $academicAnalysis = [
            'has_sufficient_data' => $totalAnswered >= 3,
            'strongest_area'      => $strongestArea ? [
                'name'     => $strongestArea['subject_name'],
                'accuracy' => $strongestArea['accuracy'],
                'answered' => $strongestArea['total_answered'],
            ] : null,
            'needs_practice_area' => ($weakestArea && $weakestArea['accuracy'] < 70) ? [
                'name'     => $weakestArea['subject_name'],
                'accuracy' => $weakestArea['accuracy'],
                'answered' => $weakestArea['total_answered'],
            ] : null,
            'pedagogical_advice'  => $totalAnswered > 0
                ? "Has resuelto {$totalAnswered} reactivos oficiales. " . ($accuracy >= 75 ? "¡Excelente nivel de comprensión lectora!" : "Continúa practicando textos discontinuos y argumentación para alcanzar tu meta.")
                : "Aún no registras preguntas resueltas en este período. Comienza una sesión de práctica para activar tu análisis.",
        ];

        // Actividad reciente (últimas 10 respuestas)
        $recentAnswers = DB::connection('question_bank')
            ->table('student_answers as sa')
            ->join('questions as q', 'sa.question_id', '=', 'q.id')
            ->join('subjects as s', 'q.subject_id', '=', 's.id')
            ->where('sa.student_id', $studentId)
            ->orderBy('sa.answered_at', 'desc')
            ->limit(10)
            ->select(
                'sa.id as answer_id',
                'sa.question_id',
                'q.statement',
                's.name as subject_name',
                'sa.selected_option_letter',
                'sa.is_correct',
                'sa.time_spent_seconds',
                'sa.answered_at'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'student_id'         => $studentId,
                'period'             => $period,
                'summary'            => [
                    'total_answered'     => $totalAnswered,
                    'correct_count'      => $correctCount,
                    'incorrect_count'    => $incorrectCount,
                    'accuracy'           => $accuracy,
                    'total_time_seconds' => (int) $totalTimeSeconds,
                    'streak_days'        => $realStreak,
                ],
                'by_subject'         => $bySubject->values()->all(),
                'daily_activity'     => $dailyActivity->values()->all(),
                'academic_analysis'  => $academicAnalysis,
                'recent_activity'    => $recentAnswers,
            ],
        ]);
    }
}
