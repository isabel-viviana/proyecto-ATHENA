<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuestionController extends Controller
{
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

        // 3. Búsqueda por texto en enunciado (case-insensitive con ILIKE en PostgreSQL)
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('statement', 'ILIKE', "%{$search}%");
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
     * Actualiza el enunciado, opciones, materia, tema, clave y justificación de una pregunta.
     *
     * PUT /api/v1/admin/questions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $question = Question::with(['options', 'stimulus'])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        $validated = $request->validate([
            'statement'      => ['nullable', 'string', 'min:5'],
            'explanation'    => ['nullable', 'string'],
            'subject_id'     => ['nullable', 'integer', 'in:1,2,3,4,5'],
            'topic_id'       => ['nullable', 'integer'],
            'difficulty'     => ['nullable', 'string', 'in:easy,medium,hard'],
            'key_status'     => ['nullable', 'string', 'in:unverified,verified,disputed'],
            'correct_option' => ['nullable', 'string', 'in:A,B,C,D'],
            'options'        => ['nullable', 'array'],
            'options.*.option_letter' => ['required_with:options', 'string', 'in:A,B,C,D'],
            'options.*.option_text'   => ['required_with:options', 'string'],
            'options.*.is_correct'    => ['nullable', 'boolean'],
        ]);

        // 1. Validar restricción foránea compuesta: si tiene estímulo, la materia debe coincidir con la del estímulo
        $targetSubjectId = $validated['subject_id'] ?? $question->subject_id;
        if ($question->stimulus_id && $targetSubjectId) {
            $stimulus = $question->stimulus ?: Stimulus::find($question->stimulus_id);
            if ($stimulus && (int) $stimulus->subject_id !== (int) $targetSubjectId) {
                return response()->json([
                    'success' => false,
                    'message' => "La pregunta está vinculada a un estímulo de lectura cuya área es '{$stimulus->subject_id}'. No se puede cambiar el área sin modificar el estímulo asociado.",
                ], 422);
            }
        }

        // 2. Validar que si topic_id existe, coincida con subject_id en topics
        if (!empty($validated['topic_id'])) {
            $topicExists = Topic::where('id', $validated['topic_id'])
                ->where('subject_id', $targetSubjectId)
                ->exists();
            if (! $topicExists) {
                return response()->json([
                    'success' => false,
                    'message' => "El tema seleccionado no pertenece al área académica asignada.",
                ], 422);
            }
        }

        // 3. Resolver estado de clave y respuesta correcta
        $keyStatus = $validated['key_status'] ?? $question->key_status;
        $correctLetter = $validated['correct_option'] ?? null;

        if ($correctLetter) {
            $correctLetter = strtoupper(trim($correctLetter));
        }

        // Si se marca explícitamente como verificada, debe especificarse una respuesta correcta
        if ($keyStatus === 'verified' && empty($correctLetter)) {
            // Verificar si alguna opción viene con is_correct = true
            if (!empty($validated['options'])) {
                foreach ($validated['options'] as $opt) {
                    if (!empty($opt['is_correct'])) {
                        $correctLetter = strtoupper(trim($opt['option_letter']));
                        break;
                    }
                }
            }
            if (empty($correctLetter)) {
                // Verificar las opciones ya existentes en base de datos
                $existingCorrect = $question->options->firstWhere('is_correct', true);
                if ($existingCorrect) {
                    $correctLetter = $existingCorrect->option_letter;
                }
            }

            if (empty($correctLetter)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Para marcar la clave como verificada, debe seleccionar una opción de respuesta correcta (A, B, C o D).',
                ], 422);
            }
        }

        try {
            DB::connection('question_bank')->transaction(function () use ($question, $validated, $keyStatus, $correctLetter, $targetSubjectId) {
                // Actualizar campos principales de la pregunta
                $updateData = [];

                if (array_key_exists('statement', $validated) && $validated['statement'] !== null) {
                    $updateData['statement'] = trim($validated['statement']);
                }

                if (array_key_exists('explanation', $validated)) {
                    $updateData['explanation'] = $validated['explanation'];
                }

                if (array_key_exists('subject_id', $validated) && $validated['subject_id'] !== null) {
                    $updateData['subject_id'] = (int) $targetSubjectId;
                }

                if (array_key_exists('topic_id', $validated)) {
                    $updateData['topic_id'] = $validated['topic_id'] ? (int) $validated['topic_id'] : null;
                }

                if (!empty($validated['difficulty'])) {
                    $updateData['difficulty'] = $validated['difficulty'];
                }

                $updateData['key_status'] = $keyStatus;

                $question->update($updateData);

                // Actualizar o crear opciones de respuesta
                if (!empty($validated['options']) && is_array($validated['options'])) {
                    foreach ($validated['options'] as $optData) {
                        $letter = strtoupper(trim($optData['option_letter']));
                        $isCorrect = ($keyStatus === 'verified' && $correctLetter === $letter);

                        $option = QuestionOption::where('question_id', $question->id)
                            ->where('option_letter', $letter)
                            ->first();

                        if ($option) {
                            $option->update([
                                'option_text' => trim($optData['option_text']),
                                'is_correct'  => $isCorrect,
                            ]);
                        } else {
                            QuestionOption::create([
                                'id'            => (string) Str::uuid(),
                                'question_id'   => $question->id,
                                'option_letter' => $letter,
                                'option_text'   => trim($optData['option_text']),
                                'is_correct'    => $isCorrect,
                            ]);
                        }
                    }
                } elseif ($correctLetter) {
                    // Si no se pasaron textos de opciones pero sí se actualizó la opción correcta
                    foreach ($question->options as $option) {
                        $isCorrect = ($keyStatus === 'verified' && $option->option_letter === $correctLetter);
                        if ($option->is_correct !== $isCorrect) {
                            $option->update(['is_correct' => $isCorrect]);
                        }
                    }
                }
            });

            $freshQuestion = Question::with(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic'])->find($question->id);

            return response()->json([
                'success' => true,
                'message' => 'Pregunta actualizada exitosamente.',
                'data'    => $freshQuestion,
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la pregunta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprueba pedagógicamente una pregunta para el banco tras verificar criterios.
     *
     * POST /api/v1/admin/questions/{id}/approve
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $question = Question::with(['options', 'stimulus'])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        // Criterio 1: Enunciado válido
        if (empty(trim($question->statement)) || mb_strlen(trim($question->statement)) < 10) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede aprobar la pregunta: el enunciado no cumple con los criterios mínimos de contenido (mínimo 10 caracteres).',
            ], 422);
        }

        // Criterio 2: Opciones completas (al menos 4 opciones y con texto)
        $options = $question->options;
        if ($options->count() < 4) {
            return response()->json([
                'success' => false,
                'message' => "No se puede aprobar la pregunta: debe tener al menos 4 opciones de respuesta registradas (actualmente tiene {$options->count()}).",
            ], 422);
        }

        foreach ($options as $opt) {
            if (empty(trim($opt->option_text))) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede aprobar la pregunta: la opción {$opt->option_letter} no contiene texto.",
                ], 422);
            }
        }

        // Criterio 3: Clave verificada o no verificada (Regla 8: validación flexible)
        if ($question->key_status === 'verified') {
            $correctOptions = $options->where('is_correct', true);
            if ($correctOptions->count() !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede aprobar la pregunta: al estar marcada como verificada, debe existir exactamente una opción correcta (actualmente hay {$correctOptions->count()}).",
                ], 422);
            }
        }
        // Si es 'unverified', se permite la aprobación pedagógica manteniendo 'unverified' sin inventar respuesta.

        // Criterio 4: Materia válida
        if (empty($question->subject_id) || $question->subject_id < 1 || $question->subject_id > 5) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede aprobar la pregunta: el área académica no es válida dentro del marco oficial ICFES.',
            ], 422);
        }

        // Criterio 5: Si tiene estímulo, validar integridad
        if ($question->stimulus_id && ! $question->stimulus) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede aprobar la pregunta: el estímulo referenciado no existe.',
            ], 422);
        }

        // Aplicar aprobación: cambiar estado a 'review' (aprobada pedagógicamente para calibración, sin publicar directamente)
        $question->update([
            'status' => 'review',
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Pregunta revisada y aprobada pedagógicamente para el banco oficial!',
            'data'    => $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic']),
        ]);
    }

    /**
     * Aprueba pedagógicamente múltiples preguntas seleccionadas.
     * Soporta éxito parcial: si una pregunta falla validaciones, no revierte las demás.
     *
     * POST /api/v1/admin/questions/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_ids'   => 'required|array|min:1',
            'question_ids.*' => 'required|uuid',
        ]);

        $ids = $validated['question_ids'];
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

            // Validación de enunciado
            if (empty(trim($question->statement)) || mb_strlen(trim($question->statement)) < 10) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => 'El enunciado debe tener al menos 10 caracteres.',
                ];
                continue;
            }

            // Validación de opciones
            $options = $question->options;
            if ($options->count() < 4) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => "Requiere al menos 4 opciones de respuesta (tiene {$options->count()}).",
                ];
                continue;
            }

            $hasEmptyOption = false;
            foreach ($options as $opt) {
                if (empty(trim($opt->option_text))) {
                    $rejected[] = [
                        'id'     => $id,
                        'order'  => $question->order_in_group,
                        'reason' => "La opción {$opt->option_letter} no contiene texto.",
                    ];
                    $hasEmptyOption = true;
                    break;
                }
            }
            if ($hasEmptyOption) {
                continue;
            }

            // Validación de materia
            if (empty($question->subject_id) || $question->subject_id < 1 || $question->subject_id > 5) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => 'El área académica asignada no es válida.',
                ];
                continue;
            }

            // Validación de clave (flexible)
            if ($question->key_status === 'verified') {
                $correctCount = $options->where('is_correct', true)->count();
                if ($correctCount !== 1) {
                    $rejected[] = [
                        'id'     => $id,
                        'order'  => $question->order_in_group,
                        'reason' => "Marcada como verificada pero tiene {$correctCount} opciones correctas (debe ser 1).",
                    ];
                    continue;
                }
            }

            // Si tiene estímulo, verificar que el estímulo exista
            if ($question->stimulus_id && ! $question->stimulus) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => 'El estímulo referenciado no existe en el sistema.',
                ];
                continue;
            }

            try {
                $question->update(['status' => 'review']);
                $approvedIds[] = $question->id;
            } catch (Throwable $e) {
                $rejected[] = [
                    'id'     => $id,
                    'order'  => $question->order_in_group,
                    'reason' => 'Error al guardar estado: ' . $e->getMessage(),
                ];
            }
        }

        $totalReceived = count($ids);
        $totalApproved = count($approvedIds);
        $totalRejected = count($rejected);

        return response()->json([
            'success' => true,
            'message' => "Aprobación masiva procesada: {$totalApproved} aprobadas, {$totalRejected} rechazadas de {$totalReceived} seleccionadas.",
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
     * Rechaza o descarta un reactivo extraído sin eliminar estímulos ni preguntas asociadas.
     *
     * POST /api/v1/admin/questions/{id}/reject
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $question = Question::with(['stimulus'])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        // Regla: Rechazar pasa el estado a 'archived' dentro del enum de PostgreSQL permitido
        $question->update([
            'status' => 'archived',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'La pregunta fue rechazada y archivada. Los estímulos compartidos permanecen intactos.',
            'data'    => $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets']),
        ]);
    }
}
