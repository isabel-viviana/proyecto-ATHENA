<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\IqpeAdminDecision;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\IqpeAdminWorkflowService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class QuestionController extends Controller
{
    public function __construct(
        protected IqpeAdminWorkflowService $workflowService
    ) {}

    protected function canManageQuestions(Request $request): bool
    {
        $admin = $request->attributes->get('auth_admin', []);
        $role = strtolower(trim((string) ($admin['role'] ?? '')));
        return in_array($role, ['admin', 'superadmin', 'super_admin', 'docente', 'coordinador'], true);
    }
    /**
     * Listado paginado de preguntas reales con filtros por estado, materia y búsqueda por enunciado.
     *
     * GET /api/v1/admin/questions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Question::with([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
            'topic',
            'sourceDocument',
        ]);

        // 1. Filtro por Estado
        // Estados posibles: draft, review, published, archived
        $statusParam = $request->input('status', 'official');

        if ($statusParam === 'official' || $statusParam === 'Todas' || empty($statusParam)) {
            // Por defecto en el banco de preguntas oficial se muestran reactivos validados: review y published
            $query->whereIn('status', ['review', 'published']);
        } elseif ($statusParam === 'all') {
            // Consultar todo sin filtro de estado para administración
        } elseif (in_array($statusParam, ['draft', 'review', 'published', 'archived'])) {
            $query->where('status', $statusParam);
        }

        // 2. Filtro por Materia (acepta ID numérico o nombre oficial)
        $subjectInput = $request->input('subject_id') ?: $request->input('subject');
        if ($subjectInput && $subjectInput !== 'Todas') {
            if (is_numeric($subjectInput)) {
                $query->where('subject_id', (int) $subjectInput);
            } else {
                $matchedSubject = Subject::whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $subjectInput))])->first();
                if ($matchedSubject) {
                    $query->where('subject_id', $matchedSubject->id);
                } else {
                    $nameMap = [
                        'Lectura Crítica' => 1,
                        'Matemáticas' => 2,
                        'Ciencias Naturales' => 3,
                        'Sociales y Ciudadanas' => 4,
                        'Inglés' => 5,
                    ];
                    if (isset($nameMap[$subjectInput])) {
                        $query->where('subject_id', $nameMap[$subjectInput]);
                    }
                }
            }
        }

        // 3. Búsqueda por texto en enunciado (portable entre PostgreSQL ILIKE y SQLite LIKE)
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $op = DB::connection('question_bank')->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where('statement', $op, "%{$search}%");
        }

        // 3.1 Filtro opcional por documento fuente
        if ($request->filled('source_document_id') && Str::isUuid((string) $request->input('source_document_id'))) {
            $query->where('source_document_id', (string) $request->input('source_document_id'));
        }

        // 4. Ordenamiento: por defecto conserva el orden original del cuadernillo (1 → N)
        $sort = $request->input('sort', 'order_asc');
        if ($sort === 'recent') {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('order_in_group', 'asc')->orderBy('created_at', 'asc');
        }

        // 5. Paginación
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $page = max(1, (int) $request->input('page', 1));
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // 6. Estadísticas y conteos reales en base de datos Supabase
        $stats = [
            'total_bank'      => Question::whereIn('status', ['review', 'published'])->count(),
            'published_count' => Question::where('status', 'published')->count(),
            'review_count'    => Question::where('status', 'review')->count(),
            'draft_count'     => Question::where('status', 'draft')->count(),
            'archived_count'  => Question::where('status', 'archived')->count(),
            'verified_count'  => Question::whereIn('status', ['review', 'published'])->where('key_status', 'verified')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'stats'   => $stats,
        ]);
    }

    /**
     * Consulta el detalle completo de una pregunta por su UUID.
     *
     * GET /api/v1/admin/questions/{id}
     */
    public function show(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de pregunta no válido.',
            ], 422);
        }

        $question = Question::with(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic', 'sourceDocument'])
            ->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $question,
        ]);
    }

    /**
     * Crea manualmente una nueva pregunta en Supabase PostgreSQL siguiendo el flujo
     * oficial de revisión y publicación (status inicial 'draft', nunca publicada automáticamente).
     *
     * POST /api/v1/admin/questions
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->canManageQuestions($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para crear preguntas en el banco.',
            ], 403);
        }

        $diffMap = [
            'fácil'   => 'easy',
            'facil'   => 'easy',
            'easy'    => 'easy',
            'media'   => 'medium',
            'medio'   => 'medium',
            'medium'  => 'medium',
            'difícil' => 'hard',
            'dificil' => 'hard',
            'hard'    => 'hard',
        ];
        if ($request->filled('difficulty')) {
            $rawDiff = mb_strtolower(trim((string) $request->input('difficulty')));
            if (isset($diffMap[$rawDiff])) {
                $request->merge(['difficulty' => $diffMap[$rawDiff]]);
            }
        }

        if (! $request->filled('subject_id') && $request->filled('subject')) {
            $subInput = $request->input('subject');
            if (is_numeric($subInput)) {
                $request->merge(['subject_id' => (int) $subInput]);
            } else {
                $foundSub = Subject::whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $subInput))])->first();
                if ($foundSub) {
                    $request->merge(['subject_id' => (int) $foundSub->id]);
                }
            }
        }

        if (! $request->has('options') && ($request->has('optionA') || $request->has('option_a'))) {
            $correctOpt = strtoupper(trim((string) ($request->input('correct_option') ?? $request->input('correctOption') ?? 'A')));
            $request->merge([
                'correct_option' => $correctOpt,
                'options' => [
                    ['option_letter' => 'A', 'option_text' => (string) ($request->input('optionA') ?? $request->input('option_a') ?? '')],
                    ['option_letter' => 'B', 'option_text' => (string) ($request->input('optionB') ?? $request->input('option_b') ?? '')],
                    ['option_letter' => 'C', 'option_text' => (string) ($request->input('optionC') ?? $request->input('option_c') ?? '')],
                    ['option_letter' => 'D', 'option_text' => (string) ($request->input('optionD') ?? $request->input('option_d') ?? '')],
                ],
            ]);
        }

        if (! $request->filled('correct_option') && $request->filled('correctOption')) {
            $request->merge(['correct_option' => strtoupper(trim((string) $request->input('correctOption')))]);
        }

        $validated = $request->validate([
            'subject_id'              => ['required', 'integer', 'min:1'],
            'topic_id'                => ['nullable', 'integer', 'min:1'],
            'difficulty'              => ['nullable', 'string', 'in:easy,medium,hard'],
            'statement'               => ['required', 'string', 'min:10', 'max:10000'],
            'explanation'             => ['nullable', 'string', 'max:10000'],
            'correct_option'          => ['required', 'string', 'in:A,B,C,D'],
            'options'                 => ['required', 'array', 'size:4'],
            'options.*.option_letter' => ['required', 'string', 'in:A,B,C,D'],
            'options.*.option_text'   => ['required', 'string', 'min:1', 'max:4000'],
            'stimulus_id'             => ['nullable', 'uuid'],
            'stimulus_title'          => ['nullable', 'string', 'max:255'],
            'stimulus_content_text'   => ['nullable', 'string', 'max:20000'],
            'stimulus_instructions'   => ['nullable', 'string', 'max:1000'],
        ], [
            'subject_id.required'   => 'Debes seleccionar un área académica.',
            'statement.required'    => 'El enunciado de la pregunta es obligatorio.',
            'statement.min'         => 'El enunciado debe tener al menos 10 caracteres.',
            'correct_option.in'     => 'La respuesta correcta debe ser A, B, C o D.',
            'options.size'          => 'La pregunta debe incluir exactamente las 4 opciones (A, B, C y D).',
        ]);

        $subject = Subject::find((int) $validated['subject_id']);
        if (! $subject) {
            return response()->json([
                'success' => false,
                'message' => 'El área académica seleccionada no existe en el catálogo.',
            ], 422);
        }

        $topicId = null;
        if (! empty($validated['topic_id'])) {
            $topic = Topic::where('id', (int) $validated['topic_id'])
                ->where('subject_id', $subject->id)
                ->first();

            if (! $topic) {
                return response()->json([
                    'success' => false,
                    'message' => 'El tema seleccionado no pertenece al área académica elegida.',
                ], 422);
            }
            $topicId = (int) $topic->id;
        }

        $optionsByLetter = [];
        foreach ($validated['options'] as $opt) {
            $letter = strtoupper(trim((string) $opt['option_letter']));
            $text = trim((string) $opt['option_text']);
            if ($text === '') {
                return response()->json([
                    'success' => false,
                    'message' => "El texto de la opción {$letter} no puede estar vacío.",
                ], 422);
            }
            $optionsByLetter[$letter] = $text;
        }

        if (count(array_diff(['A', 'B', 'C', 'D'], array_keys($optionsByLetter))) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debes proporcionar las cuatro opciones A, B, C y D.',
            ], 422);
        }

        if (count(array_unique(array_map('mb_strtolower', array_values($optionsByLetter)))) < 4) {
            return response()->json([
                'success' => false,
                'message' => 'Las cuatro opciones de respuesta (A, B, C y D) deben tener textos distintos.',
            ], 422);
        }

        $stimulusId = null;
        if (! empty($validated['stimulus_id'])) {
            $existingStimulus = Stimulus::find($validated['stimulus_id']);
            if (! $existingStimulus) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estímulo seleccionado no existe.',
                ], 422);
            }
            if ((int) $existingStimulus->subject_id !== (int) $subject->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estímulo seleccionado pertenece a otra área académica.',
                ], 422);
            }
            $stimulusId = $existingStimulus->id;
        }

        $correctLetter = strtoupper(trim((string) $validated['correct_option']));
        $admin = $request->attributes->get('auth_admin', []);

        $createdQuestion = DB::connection('question_bank')->transaction(function () use (
            $subject,
            $topicId,
            $validated,
            $optionsByLetter,
            $correctLetter,
            $stimulusId,
            $admin
        ) {
            $finalStimulusId = $stimulusId;

            if (! $finalStimulusId && ! empty(trim((string) ($validated['stimulus_content_text'] ?? '')))) {
                $newStimulus = Stimulus::create([
                    'id'                 => (string) Str::uuid(),
                    'subject_id'         => $subject->id,
                    'source_document_id' => null,
                    'title'              => ! empty($validated['stimulus_title']) ? trim((string) $validated['stimulus_title']) : "Texto de apoyo — {$subject->name}",
                    'content_text'       => trim((string) $validated['stimulus_content_text']),
                    'instructions'       => ! empty($validated['stimulus_instructions']) ? trim((string) $validated['stimulus_instructions']) : 'Responda la pregunta de acuerdo con la siguiente información:',
                    'media_type'         => 'none',
                    'created_at'         => now(),
                ]);
                $finalStimulusId = $newStimulus->id;
            }

            $nextOrder = ((int) Question::where('subject_id', $subject->id)->max('order_in_group')) + 1;
            $questionUuid = (string) Str::uuid();

            $question = Question::create([
                'id'                 => $questionUuid,
                'root_question_id'   => $questionUuid,
                'version'            => 1,
                'is_current_version' => true,
                'subject_id'         => $subject->id,
                'topic_id'           => $topicId,
                'stimulus_id'        => $finalStimulusId,
                'source_document_id' => null,
                'order_in_group'     => $nextOrder,
                'statement'          => trim((string) $validated['statement']),
                'difficulty'         => $validated['difficulty'] ?? 'medium',
                'explanation'        => ! empty($validated['explanation']) ? trim((string) $validated['explanation']) : null,
                'key_status'         => 'verified',
                'status'             => 'draft',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $letter) {
                QuestionOption::create([
                    'id'            => (string) Str::uuid(),
                    'question_id'   => $question->id,
                    'option_letter' => $letter,
                    'option_text'   => $optionsByLetter[$letter],
                    'is_correct'    => $letter === $correctLetter,
                    'created_at'    => now(),
                ]);
            }

            IqpeAdminDecision::create([
                'question_id'             => $question->id,
                'job_id'                  => null,
                'action'                  => 'manual_key_set',
                'previous_status'         => null,
                'new_status'              => 'draft',
                'previous_key_status'     => null,
                'new_key_status'          => 'verified',
                'previous_correct_letter' => null,
                'new_correct_letter'      => $correctLetter,
                'manual_key_assigned'     => true,
                'admin_id'                => (string) ($admin['id'] ?? $admin['sub'] ?? 'admin'),
                'admin_name'              => $admin['full_name'] ?? $admin['name'] ?? 'Administrador',
                'admin_email'             => $admin['email'] ?? null,
                'admin_role'              => $admin['role'] ?? 'admin',
                'reason'                  => 'Creación manual de pregunta con clave verificada por administración.',
                'notes'                   => 'Reactivo registrado en estado borrador para flujo de revisión y publicación.',
                'changes_diff'            => ['created_manually' => true, 'correct_option' => $correctLetter],
            ]);

            return $question->load(['options', 'stimulus', 'subject', 'topic']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Pregunta creada exitosamente en Supabase en estado de revisión inicial (borrador). Puedes aprobarla y publicarla desde la bandeja.',
            'data'    => $createdQuestion,
        ], 201);
    }

    /**
     * Actualiza el enunciado, opciones, materia, tema, clave y justificación de una pregunta
     * conservando trazabilidad de modificaciones y evitando alteraciones silenciosas de claves.
     *
     * PUT /api/v1/admin/questions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageQuestions($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para editar preguntas.',
            ], 403);
        }

        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de pregunta no válido.',
            ], 422);
        }

        $question = Question::with(['options', 'stimulus'])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        $validated = $request->validate([
            'statement'               => ['nullable', 'string', 'min:5'],
            'explanation'             => ['nullable', 'string'],
            'subject_id'              => ['nullable', 'integer', 'min:1'],
            'topic_id'                => ['nullable', 'integer'],
            'difficulty'              => ['nullable', 'string', 'in:easy,medium,hard'],
            'key_status'              => ['nullable', 'string', 'in:unverified,verified,disputed'],
            'correct_option'          => ['nullable', 'string', 'in:A,B,C,D'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'reason'                  => ['nullable', 'string', 'max:2000'],
            'key_change_reason'       => ['nullable', 'string', 'max:2000'],
            'manual_key_reason'       => ['nullable', 'string', 'max:2000'],
            'confirm_official_key'    => ['nullable', 'boolean'],
            'options'                 => ['nullable', 'array'],
            'options.*.option_letter' => ['required_with:options', 'string', 'in:A,B,C,D'],
            'options.*.option_text'   => ['required_with:options', 'string'],
            'options.*.is_correct'    => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['subject_id']) && ! Subject::where('id', (int) $validated['subject_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El área académica seleccionada no existe.',
            ], 422);
        }

        try {
            $admin = $request->attributes->get('auth_admin', []);
            $freshQuestion = $this->workflowService->editQuestion($question, $validated, $admin);

            return response()->json([
                'success' => true,
                'message' => 'Pregunta actualizada exitosamente con registro de trazabilidad.',
                'data'    => $freshQuestion,
            ]);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la pregunta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprueba administrativamente una pregunta tras validar ausencia de bloqueos críticos
     * y registrar quién aprobó, cuándo y qué observaciones aplican (cambia a status='review', sin publicar).
     *
     * POST /api/v1/admin/questions/{id}/approve
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageQuestions($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para aprobar preguntas.',
            ], 403);
        }

        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de pregunta no válido.',
            ], 422);
        }

        $validated = $request->validate([
            'notes'                => ['nullable', 'string', 'max:2000'],
            'acknowledge_warnings' => ['nullable', 'boolean'],
        ]);

        $question = Question::with(['options', 'stimulus'])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        $admin = $request->attributes->get('auth_admin', []);
        $approved = $this->workflowService->approveQuestion($question, $admin, $validated);

        return response()->json([
            'success' => true,
            'message' => '¡Pregunta aprobada administrativamente para revisión final/publicación!',
            'data'    => $approved,
        ]);
    }

    /**
     * Aprueba administrativamente múltiples preguntas seleccionadas.
     * Bloquea individualmente cualquier reactivo con problemas críticos sin impedir aprobar los sanos.
     *
     * POST /api/v1/admin/questions/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        if (! $this->canManageQuestions($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para aprobar preguntas.',
            ], 403);
        }

        $validated = $request->validate([
            'question_ids'         => 'required|array|min:1',
            'question_ids.*'       => 'required|uuid',
            'notes'                => 'nullable|string|max:2000',
            'acknowledge_warnings' => 'nullable|boolean',
        ]);

        $ids = $validated['question_ids'];
        $admin = $request->attributes->get('auth_admin', []);
        $approvedIds = [];
        $rejected = [];

        foreach ($ids as $id) {
            $question = Question::with(['options', 'stimulus'])->find($id);

            if (! $question) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => null,
                    'reason' => 'Pregunta no encontrada.',
                ];
                continue;
            }

            try {
                $this->workflowService->approveQuestion($question, $admin, [
                    'notes'                => $validated['notes'] ?? null,
                    'acknowledge_warnings' => $validated['acknowledge_warnings'] ?? false,
                ]);
                $approvedIds[] = $question->id;
            } catch (HttpResponseException $e) {
                $body = json_decode((string) $e->getResponse()->getContent(), true);
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => $body['message'] ?? 'No cumple criterios administrativos de aprobación.',
                ];
            } catch (Throwable $e) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => 'Error al aprobar: ' . $e->getMessage(),
                ];
            }
        }

        $totalReceived = count($ids);
        $totalApproved = count($approvedIds);
        $totalRejected = count($rejected);

        return response()->json([
            'success' => true,
            'message' => "Aprobación masiva procesada: {$totalApproved} aprobadas, {$totalRejected} bloqueadas de {$totalReceived} seleccionadas.",
            'data'    => [
                'total_received' => $totalReceived,
                'total_approved' => $totalApproved,
                'total_rejected' => $totalRejected,
                'approved_ids'   => $approvedIds,
                'rejected'       => $rejected,
            ],
        ]);
    }

    /**
     * Rechaza y archiva un reactivo exigiendo motivo obligatorio y conservando todo su historial de IA.
     *
     * POST /api/v1/admin/questions/{id}/reject
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageQuestions($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para rechazar preguntas.',
            ], 403);
        }

        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de pregunta no válido.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ], [
            'reason.required' => 'El motivo de rechazo es obligatorio.',
            'reason.min'      => 'El motivo de rechazo debe tener al menos 5 caracteres.',
        ]);

        $question = Question::with(['stimulus'])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        $admin = $request->attributes->get('auth_admin', []);
        $rejected = $this->workflowService->rejectQuestion(
            $question,
            $admin,
            $validated['reason'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'La pregunta fue rechazada con registro administrativo y archivada. Las resoluciones y auditorías permanecen intactas.',
            'data'    => $rejected,
        ]);
    }
}
