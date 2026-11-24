<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Stimulus;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    protected array $defaultSubjectVisuals = [
        1 => [
            'icon'        => 'fa-solid fa-book-open',
            'color'       => '#3b82f6',
            'description' => 'Comprensión semántica, análisis argumentativo, textos continuos y discontinuos.',
        ],
        2 => [
            'icon'        => 'fa-solid fa-calculator',
            'color'       => '#10b981',
            'description' => 'Razonamiento cuantitativo, álgebra, funciones, geometría y estadística.',
        ],
        3 => [
            'icon'        => 'fa-solid fa-flask',
            'color'       => '#a855f7',
            'description' => 'Indagación científica, biología, física, química y ciencia, tecnología y sociedad.',
        ],
        4 => [
            'icon'        => 'fa-solid fa-landmark',
            'color'       => '#f59e0b',
            'description' => 'Pensamiento social, historia, constitución política y competencias ciudadanas.',
        ],
        5 => [
            'icon'        => 'fa-solid fa-earth-americas',
            'color'       => '#06b6d4',
            'description' => 'Competencia comunicativa y comprensión lectora en lengua extranjera (A1 a B1+).',
        ],
    ];

    protected function canManageCatalog(Request $request): bool
    {
        $admin = $request->attributes->get('auth_admin', []);
        $role = strtolower(trim((string) ($admin['role'] ?? '')));
        return in_array($role, ['admin', 'superadmin', 'super_admin', 'docente', 'coordinador'], true);
    }

    protected function formatTopic(Topic $t, array $questionCountsByTopic = [], ?string $subjectName = null): array
    {
        $qCount = $questionCountsByTopic[$t->id] ?? Question::where('topic_id', $t->id)->count();
        $isActive = $t->is_verified === null ? true : (bool) $t->is_verified;

        return [
            'id'              => (int) $t->id,
            'subject_id'      => (int) $t->subject_id,
            'subject_name'    => $subjectName ?? ($t->relationLoaded('subject') && $t->subject ? $t->subject->name : null),
            'name'            => $t->name,
            'slug'            => $t->slug ?: Str::slug($t->name),
            'description'     => $t->description ?: '',
            'desc'            => $t->description ?: '',
            'is_verified'     => $isActive,
            'is_active'       => $isActive,
            'status'          => $isActive ? 'Activo' : 'Inactivo',
            'questions_count' => (int) $qCount,
            'questionsCount'  => (int) $qCount,
            'created_at'      => $t->created_at?->toIso8601String(),
        ];
    }

    protected function formatSubject(
        Subject $s,
        $topicsCollection,
        array $questionCountsBySubject = [],
        array $publishedCountsBySubject = [],
        array $questionCountsByTopic = []
    ): array {
        $visual = $this->defaultSubjectVisuals[$s->id] ?? [
            'icon'        => 'fa-solid fa-book-bookmark',
            'color'       => '#10b981',
            'description' => 'Área académica registrada en el banco oficial ICFES.',
        ];

        $subjectTopics = $topicsCollection
            ->where('subject_id', $s->id)
            ->values()
            ->map(fn(Topic $t) => $this->formatTopic($t, $questionCountsByTopic, $s->name))
            ->all();

        $totalQ = (int) ($questionCountsBySubject[$s->id] ?? 0);
        $pubQ = (int) ($publishedCountsBySubject[$s->id] ?? 0);

        return [
            'id'                        => (int) $s->id,
            'name'                      => $s->name,
            'slug'                      => $s->slug ?? $s->code ?? Str::slug($s->name),
            'description'               => $s->description ?: $visual['description'],
            'icon'                      => $visual['icon'],
            'color'                     => $visual['color'],
            'questions_count'           => $totalQ,
            'published_questions_count' => $pubQ,
            'topics_count'              => count($subjectTopics),
            'topics'                    => $subjectTopics,
            'created_at'                => $s->created_at?->toIso8601String(),
        ];
    }

    /**
     * Listado oficial de áreas académicas ICFES con sus temas y conteos reales en Supabase.
     *
     * GET /api/v1/admin/catalogs/subjects
     */
    public function subjects(): JsonResponse
    {
        $subjects = Subject::orderBy('id', 'asc')->get();
        $allTopics = Topic::orderBy('id', 'asc')->get();

        $qCountsBySubject = Question::select('subject_id', DB::raw('count(*) as total'))
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id')
            ->all();

        $pubCountsBySubject = Question::where('status', 'published')
            ->select('subject_id', DB::raw('count(*) as total'))
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id')
            ->all();

        $qCountsByTopic = Question::whereNotNull('topic_id')
            ->select('topic_id', DB::raw('count(*) as total'))
            ->groupBy('topic_id')
            ->pluck('total', 'topic_id')
            ->all();

        $data = $subjects->map(fn(Subject $s) => $this->formatSubject(
            $s,
            $allTopics,
            $qCountsBySubject,
            $pubCountsBySubject,
            $qCountsByTopic
        ))->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Crea una nueva área académica en Supabase PostgreSQL.
     *
     * POST /api/v1/admin/catalogs/subjects
     */
    public function storeSubject(Request $request): JsonResponse
    {
        if (! $this->canManageCatalog($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para crear áreas académicas.',
            ], 403);
        }

        $validated = $request->validate([
            'name'        => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:1000',
            'slug'        => 'nullable|string|max:120',
        ]);

        $cleanName = trim($validated['name']);
        $baseSlug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($cleanName);
        if ($baseSlug === '') {
            $baseSlug = 'area-' . strtolower(Str::random(6));
        }

        $existsName = Subject::whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])->exists();
        if ($existsName) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un área académica con ese nombre.',
            ], 422);
        }

        $slug = $baseSlug;
        if (Schema::connection('question_bank')->hasColumn('subjects', 'slug')) {
            $counter = 1;
            while (Subject::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
        }

        $insertData = [
            'name' => $cleanName,
        ];

        if (DB::connection('question_bank')->getDriverName() !== 'pgsql') {
            $insertData['id'] = max(5, (int) Subject::max('id')) + 1;
        }

        if (Schema::connection('question_bank')->hasColumn('subjects', 'slug')) {
            $insertData['slug'] = $slug;
        }
        if (Schema::connection('question_bank')->hasColumn('subjects', 'code')) {
            $insertData['code'] = $slug;
        }
        if (Schema::connection('question_bank')->hasColumn('subjects', 'description')) {
            $insertData['description'] = isset($validated['description']) ? trim((string) $validated['description']) : null;
        }
        if (Schema::connection('question_bank')->hasColumn('subjects', 'created_at')) {
            $insertData['created_at'] = now();
        }

        $subject = Subject::create($insertData);

        return response()->json([
            'success' => true,
            'message' => 'Área académica creada exitosamente en Supabase.',
            'data'    => $this->formatSubject($subject, collect()),
        ], 201);
    }

    /**
     * Actualiza un área académica existente en Supabase PostgreSQL.
     *
     * PUT /api/v1/admin/catalogs/subjects/{id}
     */
    public function updateSubject(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageCatalog($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para editar áreas académicas.',
            ], 403);
        }

        $subject = Subject::find((int) $id);
        if (! $subject) {
            return response()->json([
                'success' => false,
                'message' => 'El área académica solicitada no existe.',
            ], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|min:2|max:120',
            'description' => 'nullable|string|max:1000',
            'slug'        => 'nullable|string|max:120',
        ]);

        if (isset($validated['name'])) {
            $cleanName = trim($validated['name']);
            $duplicate = Subject::whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
                ->where('id', '!=', $subject->id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra área académica con ese nombre.',
                ], 422);
            }

            $subject->name = $cleanName;

            if (Schema::connection('question_bank')->hasColumn('subjects', 'slug')) {
                $newSlug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($cleanName);
                $dupSlug = Subject::where('slug', $newSlug)->where('id', '!=', $subject->id)->exists();
                if (! $dupSlug && $newSlug !== '') {
                    $subject->slug = $newSlug;
                }
            }
        }

        if (array_key_exists('description', $validated) && Schema::connection('question_bank')->hasColumn('subjects', 'description')) {
            $subject->description = $validated['description'] !== null ? trim((string) $validated['description']) : null;
        }

        $subject->save();

        $topics = Topic::where('subject_id', $subject->id)->orderBy('id', 'asc')->get();
        $qCount = Question::where('subject_id', $subject->id)->count();
        $pubCount = Question::where('subject_id', $subject->id)->where('status', 'published')->count();

        return response()->json([
            'success' => true,
            'message' => 'Área académica actualizada exitosamente.',
            'data'    => $this->formatSubject(
                $subject,
                $topics,
                [$subject->id => $qCount],
                [$subject->id => $pubCount]
            ),
        ]);
    }

    /**
     * Elimina un área académica de Supabase verificando integridad referencial.
     *
     * DELETE /api/v1/admin/catalogs/subjects/{id}
     */
    public function destroySubject(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageCatalog($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar áreas académicas.',
            ], 403);
        }

        $subject = Subject::find((int) $id);
        if (! $subject) {
            return response()->json([
                'success' => false,
                'message' => 'El área académica solicitada no existe.',
            ], 404);
        }

        $questionsCount = Question::where('subject_id', $subject->id)->count();
        $stimuliCount = Stimulus::where('subject_id', $subject->id)->count();

        if ($questionsCount > 0 || $stimuliCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar el área '{$subject->name}' porque tiene {$questionsCount} pregunta(s) y {$stimuliCount} estímulo(s) asociados en el banco.",
            ], 422);
        }

        if ($subject->id <= 5 && Subject::count() <= 5) {
            return response()->json([
                'success' => false,
                'message' => "El área '{$subject->name}' forma parte de las 5 áreas troncales del ICFES Saber 11 y debe conservarse en el catálogo.",
            ], 422);
        }

        DB::connection('question_bank')->transaction(function () use ($subject) {
            Topic::where('subject_id', $subject->id)->delete();
            $subject->delete();
        });

        return response()->json([
            'success' => true,
            'message' => "Área '{$subject->name}' eliminada exitosamente.",
        ]);
    }

    /**
     * Listado de temas curriculares por materia.
     *
     * GET /api/v1/admin/catalogs/topics
     */
    public function topics(Request $request): JsonResponse
    {
        $query = Topic::with('subject');

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        if ($request->filled('only_active') && filter_var($request->input('only_active'), FILTER_VALIDATE_BOOLEAN)) {
            if (Schema::connection('question_bank')->hasColumn('topics', 'is_verified')) {
                $query->where('is_verified', true);
            }
        }

        $topics = $query->orderBy('id', 'asc')->get();

        $qCountsByTopic = Question::whereNotNull('topic_id')
            ->select('topic_id', DB::raw('count(*) as total'))
            ->groupBy('topic_id')
            ->pluck('total', 'topic_id')
            ->all();

        $data = $topics->map(fn(Topic $t) => $this->formatTopic($t, $qCountsByTopic))->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Crea un nuevo tema curricular en Supabase PostgreSQL.
     *
     * POST /api/v1/admin/catalogs/topics
     */
    public function storeTopic(Request $request): JsonResponse
    {
        if (! $this->canManageCatalog($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para crear temas curriculares.',
            ], 403);
        }

        if (! $request->filled('description') && $request->filled('desc')) {
            $request->merge(['description' => $request->input('desc')]);
        }

        $validated = $request->validate([
            'subject_id'  => 'required|integer',
            'name'        => 'required|string|min:2|max:180',
            'description' => 'nullable|string|max:1000',
            'is_verified' => 'nullable|boolean',
        ]);

        $subject = Subject::find((int) $validated['subject_id']);
        if (! $subject) {
            return response()->json([
                'success' => false,
                'message' => 'El área académica seleccionada no existe.',
            ], 422);
        }

        $cleanName = trim($validated['name']);
        $baseSlug = Str::slug($cleanName);
        if ($baseSlug === '') {
            $baseSlug = 'tema-' . strtolower(Str::random(6));
        }

        $duplicateName = Topic::where('subject_id', $subject->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
            ->exists();

        if ($duplicateName) {
            return response()->json([
                'success' => false,
                'message' => "Ya existe el tema '{$cleanName}' dentro de {$subject->name}.",
            ], 422);
        }

        $slug = $baseSlug;
        if (Schema::connection('question_bank')->hasColumn('topics', 'slug')) {
            $counter = 1;
            while (Topic::where('subject_id', $subject->id)->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
        }

        $insertData = [
            'subject_id' => $subject->id,
            'name'       => $cleanName,
        ];

        if (DB::connection('question_bank')->getDriverName() !== 'pgsql') {
            $insertData['id'] = ((int) Topic::max('id')) + 1;
        }

        if (Schema::connection('question_bank')->hasColumn('topics', 'slug')) {
            $insertData['slug'] = $slug;
        }
        if (Schema::connection('question_bank')->hasColumn('topics', 'description')) {
            $insertData['description'] = isset($validated['description']) ? trim((string) $validated['description']) : null;
        }
        if (Schema::connection('question_bank')->hasColumn('topics', 'is_verified')) {
            $insertData['is_verified'] = array_key_exists('is_verified', $validated) ? (bool) $validated['is_verified'] : true;
        }
        if (Schema::connection('question_bank')->hasColumn('topics', 'created_at')) {
            $insertData['created_at'] = now();
        }

        $topic = Topic::create($insertData);

        return response()->json([
            'success' => true,
            'message' => 'Tema curricular creado exitosamente en Supabase.',
            'data'    => $this->formatTopic($topic, [], $subject->name),
        ], 201);
    }

    /**
     * Edita un tema curricular existente en Supabase PostgreSQL.
     *
     * PUT /api/v1/admin/catalogs/topics/{id}
     */
    public function updateTopic(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageCatalog($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para editar temas curriculares.',
            ], 403);
        }

        $topic = Topic::with('subject')->find((int) $id);
        if (! $topic) {
            return response()->json([
                'success' => false,
                'message' => 'El tema solicitado no existe.',
            ], 404);
        }

        if (! $request->filled('description') && $request->has('desc')) {
            $request->merge(['description' => $request->input('desc')]);
        }

        $validated = $request->validate([
            'subject_id'  => 'sometimes|required|integer',
            'name'        => 'sometimes|required|string|min:2|max:180',
            'description' => 'nullable|string|max:1000',
            'is_verified' => 'nullable|boolean',
        ]);

        $targetSubjectId = isset($validated['subject_id']) ? (int) $validated['subject_id'] : (int) $topic->subject_id;
        if ($targetSubjectId !== (int) $topic->subject_id) {
            if (! Subject::where('id', $targetSubjectId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El área de destino no existe.',
                ], 422);
            }
            if (Question::where('topic_id', $topic->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el área de un tema que ya tiene preguntas vinculadas.',
                ], 422);
            }
            $topic->subject_id = $targetSubjectId;
        }

        if (isset($validated['name'])) {
            $cleanName = trim($validated['name']);
            $duplicate = Topic::where('subject_id', $targetSubjectId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
                ->where('id', '!=', $topic->id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro tema con ese nombre en la misma área.',
                ], 422);
            }

            $topic->name = $cleanName;
            if (Schema::connection('question_bank')->hasColumn('topics', 'slug')) {
                $newSlug = Str::slug($cleanName);
                $dupSlug = Topic::where('subject_id', $targetSubjectId)
                    ->where('slug', $newSlug)
                    ->where('id', '!=', $topic->id)
                    ->exists();
                if (! $dupSlug && $newSlug !== '') {
                    $topic->slug = $newSlug;
                }
            }
        }

        if (array_key_exists('description', $validated) && Schema::connection('question_bank')->hasColumn('topics', 'description')) {
            $topic->description = $validated['description'] !== null ? trim((string) $validated['description']) : null;
        }

        if (array_key_exists('is_verified', $validated) && $validated['is_verified'] !== null && Schema::connection('question_bank')->hasColumn('topics', 'is_verified')) {
            $topic->is_verified = (bool) $validated['is_verified'];
        }

        $topic->save();
        $topic->load('subject');

        return response()->json([
            'success' => true,
            'message' => 'Tema actualizado exitosamente.',
            'data'    => $this->formatTopic($topic),
        ]);
    }

    /**
     * Elimina o desactiva un tema curricular respetando las relaciones con preguntas.
     *
     * DELETE /api/v1/admin/catalogs/topics/{id}
     */
    public function destroyTopic(Request $request, string $id): JsonResponse
    {
        if (! $this->canManageCatalog($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar o desactivar temas.',
            ], 403);
        }

        $topic = Topic::find((int) $id);
        if (! $topic) {
            return response()->json([
                'success' => false,
                'message' => 'El tema solicitado no existe.',
            ], 404);
        }

        $linkedQuestions = Question::where('topic_id', $topic->id)->count();

        if ($linkedQuestions > 0) {
            // Si tiene preguntas asociadas, desactivar o desasociar sin romper integridad referencial
            if (Schema::connection('question_bank')->hasColumn('topics', 'is_verified')) {
                $topic->is_verified = false;
                $topic->save();
            }

            return response()->json([
                'success'     => true,
                'deactivated' => true,
                'message'     => "El tema '{$topic->name}' tiene {$linkedQuestions} pregunta(s) asociadas; fue marcado como inactivo para conservar el historial académico.",
                'data'        => $this->formatTopic($topic),
            ]);
        }

        $topicName = $topic->name;
        $topic->delete();

        return response()->json([
            'success'     => true,
            'deactivated' => false,
            'message'     => "Tema '{$topicName}' eliminado exitosamente.",
        ]);
    }

    /**
     * Listado de estímulos disponibles por área académica para asociar a preguntas manuales.
     *
     * GET /api/v1/admin/catalogs/stimuli
     */
    public function stimuli(Request $request): JsonResponse
    {
        $query = Stimulus::query();

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $op = DB::connection('question_bank')->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where(function ($q) use ($search, $op) {
                $q->where('title', $op, "%{$search}%")
                  ->orWhere('content_text', $op, "%{$search}%");
            });
        }

        $stimuli = $query->orderBy('created_at', 'desc')
            ->limit(60)
            ->get()
            ->map(function (Stimulus $s) {
                $excerpt = Str::limit(trim((string) $s->content_text), 110);
                return [
                    'id'           => $s->id,
                    'subject_id'   => (int) $s->subject_id,
                    'title'        => $s->title ?: 'Estímulo sin título',
                    'instructions' => $s->instructions,
                    'content_text' => $s->content_text,
                    'excerpt'      => $excerpt,
                    'created_at'   => $s->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $stimuli,
        ]);
    }
}
