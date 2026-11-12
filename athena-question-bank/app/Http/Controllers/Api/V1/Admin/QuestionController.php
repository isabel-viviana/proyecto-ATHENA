<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
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
     * Actualiza el enunciado, opciones, materia, tema, clave y justificación de una pregunta
     * conservando trazabilidad de modificaciones y evitando alteraciones silenciosas de claves.
     *
     * PUT /api/v1/admin/questions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
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
            'subject_id'              => ['nullable', 'integer', 'in:1,2,3,4,5'],
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
