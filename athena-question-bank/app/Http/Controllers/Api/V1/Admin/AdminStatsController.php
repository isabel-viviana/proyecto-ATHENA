<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminStatsController extends Controller
{
    protected function normalizeRole(?string $role): string
    {
        $clean = strtolower(trim((string) $role));
        if (in_array($clean, ['superadmin', 'super_admin'], true)) {
            return 'superadmin';
        }
        if (in_array($clean, ['admin', 'support', 'docente', 'coordinador'], true)) {
            return 'admin';
        }
        return 'student';
    }

    /**
     * Estadísticas administrativas y académicas reales unificadas (MySQL Core + Supabase PostgreSQL).
     * Diferencia permisos y nivel de detalle según el rol del usuario autenticado (superadmin, admin, support).
     *
     * GET /api/v1/admin/stats
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('auth_admin', []);
        $viewerRole = $this->normalizeRole($admin['role'] ?? 'admin');
        $isSuperAdmin = $viewerRole === 'superadmin';
        $canEditContent = in_array($viewerRole, ['superadmin', 'admin', 'docente', 'coordinador'], true);

        // 1. Métricas reales de Usuarios desde MySQL Core (athena_core.users)
        $usersStats = [
            'total'          => 0,
            'active'         => 0,
            'suspended'      => 0,
            'by_role'        => [
                'student'    => 0,
                'admin'      => 0,
                'superadmin' => 0,
                'support'    => 0,
            ],
            'recent_users'   => [],
            'suspended_list' => [],
        ];

        $userNamesById = [];

        try {
            $allUsers = DB::connection('athena_core')
                ->table('users')
                ->orderBy('id', 'desc')
                ->get();

            $usersStats['total'] = $allUsers->count();

            foreach ($allUsers as $u) {
                $userNamesById[(int) $u->id] = $u->full_name;
                $role = $this->normalizeRole($u->role);
                $rawStatus = strtolower(trim((string) ($u->status ?? 'active')));
                $isSuspended = ($u->remember_token === 'SUSPENDED')
                    || in_array($rawStatus, ['blocked', 'suspended', 'suspendido', 'inactivo', 'inactive'], true);

                if ($isSuspended) {
                    $usersStats['suspended']++;
                    $usersStats['suspended_list'][] = [
                        'id'         => (int) $u->id,
                        'full_name'  => $u->full_name,
                        'email'      => $u->email,
                        'role'       => $role,
                        'status'     => 'Suspendido',
                        'created_at' => $u->created_at,
                    ];
                } else {
                    $usersStats['active']++;
                }

                if (! isset($usersStats['by_role'][$role])) {
                    $usersStats['by_role'][$role] = 0;
                }
                $usersStats['by_role'][$role]++;
            }

            $usersStats['recent_users'] = $allUsers->take(6)->map(function ($u) {
                $rawStatus = strtolower(trim((string) ($u->status ?? 'active')));
                $isSuspended = ($u->remember_token === 'SUSPENDED')
                    || in_array($rawStatus, ['blocked', 'suspended', 'suspendido', 'inactivo', 'inactive'], true);
                return [
                    'id'            => (int) $u->id,
                    'full_name'     => $u->full_name,
                    'email'         => $u->email,
                    'role'          => $this->normalizeRole($u->role),
                    'status'        => $isSuspended ? 'Suspendido' : 'Activo',
                    'last_login_at' => $u->last_login_at,
                    'created_at'    => $u->created_at,
                ];
            })->values()->all();
        } catch (Throwable) {
            // Si en entorno aislado de pruebas no está athena_core inicializado, mantiene conteos en 0
        }

        // 2. Métricas reales de Catálogo y Banco de Preguntas en Supabase (question_bank)
        $subjects = Subject::orderBy('id', 'asc')->get();
        $topics = Topic::orderBy('id', 'asc')->get();

        $totalSubjects = $subjects->count();
        $totalTopics = $topics->count();
        $activeTopics = Schema::connection('question_bank')->hasColumn('topics', 'is_verified')
            ? $topics->where('is_verified', true)->count()
            : $totalTopics;

        $statusCounts = Question::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $publishedCount = (int) ($statusCounts['published'] ?? 0);
        $reviewCount = (int) ($statusCounts['review'] ?? 0);
        $draftCount = (int) ($statusCounts['draft'] ?? 0);
        $archivedCount = (int) ($statusCounts['archived'] ?? 0);
        $totalQuestions = $publishedCount + $reviewCount + $draftCount + $archivedCount;

        $verifiedKeysCount = Question::where('key_status', 'verified')->count();
        $unverifiedKeysCount = Question::where('key_status', 'unverified')->count();
        $disputedKeysCount = Question::where('key_status', 'disputed')->count();

        $withWarningsCount = Question::whereNotNull('extraction_warnings')
            ->where('extraction_warnings', '!=', '')
            ->count();

        $totalStimuli = Stimulus::count();

        // 3. Actividad académica real (simulacros y respuestas de práctica en Supabase)
        $totalExamAttempts = 0;
        $completedExamAttempts = 0;
        $inProgressExamAttempts = 0;
        $totalStudentAnswers = 0;
        $correctStudentAnswers = 0;
        $overallAccuracy = 0.0;
        $activeStudentsCount = 0;
        $recentAttempts = [];
        $accuracyBySubject = [];

        try {
            if (Schema::connection('question_bank')->hasTable('exam_attempts')) {
                $totalExamAttempts = (int) DB::connection('question_bank')->table('exam_attempts')->count();
                $completedExamAttempts = (int) DB::connection('question_bank')->table('exam_attempts')
                    ->where('status', 'completed')
                    ->count();
                $inProgressExamAttempts = (int) DB::connection('question_bank')->table('exam_attempts')
                    ->where('status', 'in_progress')
                    ->count();

                $rawRecentAttempts = DB::connection('question_bank')->table('exam_attempts')
                    ->orderBy('created_at', 'desc')
                    ->limit(6)
                    ->get();

                foreach ($rawRecentAttempts as $att) {
                    $sid = (int) $att->student_id;
                    $recentAttempts[] = [
                        'id'              => $att->id,
                        'student_id'      => $sid,
                        'student_name'    => $userNamesById[$sid] ?? "Estudiante #{$sid}",
                        'exam_type'       => $att->exam_type,
                        'title'           => $att->title,
                        'status'          => $att->status,
                        'score_percent'   => $att->score_percent !== null ? round((float) $att->score_percent, 1) : null,
                        'correct_count'   => (int) ($att->correct_count ?? 0),
                        'total_questions' => (int) ($att->total_questions ?? 0),
                        'created_at'      => $att->created_at,
                    ];
                }
            }

            if (Schema::connection('question_bank')->hasTable('student_answers')) {
                $totalStudentAnswers = (int) DB::connection('question_bank')->table('student_answers')->count();
                $correctStudentAnswers = (int) DB::connection('question_bank')->table('student_answers')
                    ->where('is_correct', true)
                    ->count();

                if ($totalStudentAnswers > 0) {
                    $overallAccuracy = round(($correctStudentAnswers / $totalStudentAnswers) * 100, 1);
                }

                $subjectPerfRows = DB::connection('question_bank')->table('student_answers')
                    ->join('questions', 'student_answers.question_id', '=', 'questions.id')
                    ->select(
                        'questions.subject_id',
                        DB::raw('count(*) as total_answered'),
                        DB::raw('sum(case when student_answers.is_correct then 1 else 0 end) as total_correct')
                    )
                    ->groupBy('questions.subject_id')
                    ->get();

                foreach ($subjectPerfRows as $row) {
                    $ans = (int) $row->total_answered;
                    $corr = (int) $row->total_correct;
                    $accuracyBySubject[(int) $row->subject_id] = [
                        'answered' => $ans,
                        'correct'  => $corr,
                        'accuracy' => $ans > 0 ? round(($corr / $ans) * 100, 1) : null,
                    ];
                }

                $ansStudents = DB::connection('question_bank')->table('student_answers')
                    ->distinct()
                    ->pluck('student_id')
                    ->map(fn($v) => (int) $v)
                    ->all();

                $examStudents = Schema::connection('question_bank')->hasTable('exam_attempts')
                    ? DB::connection('question_bank')->table('exam_attempts')
                        ->distinct()
                        ->pluck('student_id')
                        ->map(fn($v) => (int) $v)
                        ->all()
                    : [];

                $activeStudentsCount = count(array_unique(array_merge($ansStudents, $examStudents)));
            }
        } catch (Throwable) {
            // Continuar con valores base si alguna tabla opcional no está presente
        }

        // 4. Desglose por Área Académica
        $qBySubjectAndStatus = Question::select('subject_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('subject_id', 'status')
            ->get();

        $topicsCountBySubject = $topics->groupBy('subject_id')->map->count()->all();

        $subjectsBreakdown = $subjects->map(function (Subject $s) use ($qBySubjectAndStatus, $topicsCountBySubject, $accuracyBySubject) {
            $rows = $qBySubjectAndStatus->where('subject_id', $s->id);
            $pub = (int) ($rows->firstWhere('status', 'published')->total ?? 0);
            $rev = (int) ($rows->firstWhere('status', 'review')->total ?? 0);
            $drf = (int) ($rows->firstWhere('status', 'draft')->total ?? 0);
            $arc = (int) ($rows->firstWhere('status', 'archived')->total ?? 0);
            $tot = $pub + $rev + $drf + $arc;

            $perf = $accuracyBySubject[$s->id] ?? ['answered' => 0, 'correct' => 0, 'accuracy' => null];

            return [
                'id'                  => (int) $s->id,
                'name'                => $s->name,
                'slug'                => $s->slug ?? Str::slug($s->name),
                'description'         => $s->description,
                'total_questions'     => $tot,
                'official_questions'  => $pub + $rev,
                'published_questions' => $pub,
                'review_questions'    => $rev,
                'draft_questions'     => $drf,
                'archived_questions'  => $arc,
                'topics_count'        => (int) ($topicsCountBySubject[$s->id] ?? 0),
                'answers_count'       => $perf['answered'],
                'accuracy_avg'        => $perf['accuracy'],
            ];
        })->values()->all();

        // 5. Últimas preguntas registradas en Supabase
        $recentQuestions = Question::with(['subject', 'topic'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(fn(Question $q) => [
                'id'           => $q->id,
                'statement'    => Str::limit(trim((string) $q->statement), 120),
                'subject_id'   => (int) $q->subject_id,
                'subject_name' => $q->subject?->name ?? 'Área General',
                'topic_id'     => $q->topic_id ? (int) $q->topic_id : null,
                'topic_name'   => $q->topic?->name ?? 'Sin tema específico',
                'difficulty'   => $q->difficulty ?? 'medium',
                'status'       => $q->status,
                'key_status'   => $q->key_status,
                'created_at'   => $q->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        // 6. Información operativa para Panel de Soporte (preguntas con observaciones y documentos fuente)
        $questionsWithWarnings = Question::with(['subject', 'topic'])
            ->where(function ($q) {
                $q->whereNotNull('extraction_warnings')
                  ->where('extraction_warnings', '!=', '')
                  ->orWhereIn('key_status', ['unverified', 'disputed']);
            })
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn(Question $q) => [
                'id'                  => $q->id,
                'order_in_group'      => $q->order_in_group,
                'statement'           => Str::limit(trim((string) $q->statement), 110),
                'subject_name'        => $q->subject?->name ?? 'General',
                'status'              => $q->status,
                'key_status'          => $q->key_status,
                'extraction_warnings' => $q->extraction_warnings,
                'updated_at'          => $q->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $documentsSummary = [
            'total'     => 0,
            'completed' => 0,
            'failed'    => 0,
            'recent'    => [],
        ];

        try {
            if (Schema::connection('question_bank')->hasTable('source_documents')) {
                $docs = SourceDocument::orderBy('created_at', 'desc')->get();
                $documentsSummary['total'] = $docs->count();
                $documentsSummary['completed'] = $docs->whereIn('extraction_status', ['completed', 'extracted'])->count();
                $documentsSummary['failed'] = $docs->where('extraction_status', 'failed')->count();
                $documentsSummary['recent'] = $docs->take(5)->map(fn(SourceDocument $d) => [
                    'id'                => $d->id,
                    'title'             => $d->title,
                    'extraction_status' => $d->extraction_status,
                    'created_at'        => $d->created_at?->toIso8601String(),
                ])->values()->all();
            }
        } catch (Throwable) {
            // Ignorar si no existe tabla en entorno mínimo
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'permissions' => [
                    'viewer_role'       => $viewerRole,
                    'is_superadmin'     => $isSuperAdmin,
                    'can_manage_roles'  => $isSuperAdmin,
                    'can_create_admins' => $isSuperAdmin,
                    'can_edit_content'  => $canEditContent,
                    'can_manage_users'  => in_array($viewerRole, ['superadmin', 'admin'], true),
                ],
                'users'              => $usersStats,
                'catalog'            => [
                    'total_subjects' => $totalSubjects,
                    'total_topics'   => $totalTopics,
                    'active_topics'  => $activeTopics,
                    'total_stimuli'  => $totalStimuli,
                ],
                'questions'          => [
                    'total'          => $totalQuestions,
                    'official_bank'  => $publishedCount + $reviewCount,
                    'by_status'      => [
                        'published' => $publishedCount,
                        'review'    => $reviewCount,
                        'draft'     => $draftCount,
                        'archived'  => $archivedCount,
                    ],
                    'by_key_status'  => [
                        'verified'   => $verifiedKeysCount,
                        'unverified' => $unverifiedKeysCount,
                        'disputed'   => $disputedKeysCount,
                    ],
                    'with_warnings'  => $withWarningsCount,
                ],
                'academic_activity'  => [
                    'total_exam_attempts'       => $totalExamAttempts,
                    'completed_exam_attempts'   => $completedExamAttempts,
                    'in_progress_exam_attempts' => $inProgressExamAttempts,
                    'total_student_answers'     => $totalStudentAnswers,
                    'correct_student_answers'   => $correctStudentAnswers,
                    'overall_accuracy'          => $overallAccuracy,
                    'active_students_count'     => $activeStudentsCount,
                    'recent_attempts'           => $recentAttempts,
                ],
                'subjects_breakdown' => $subjectsBreakdown,
                'recent_questions'   => $recentQuestions,
                'support_overview'   => [
                    'suspended_accounts'      => $usersStats['suspended_list'],
                    'questions_with_warnings' => $questionsWithWarnings,
                    'documents_summary'       => $documentsSummary,
                ],
            ],
        ]);
    }
}
