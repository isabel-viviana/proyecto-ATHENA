<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\IqpeAdminDecision;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Services\IqpeAdminWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IqpeReviewController extends Controller
{
    public function __construct(
        protected IqpeAdminWorkflowService $workflowService
    ) {}

    /**
     * Bandeja administrativa unificada de revisión IQPE con filtros especializados,
     * conteos por categoría y métricas de verificación, calidad y duplicados.
     *
     * GET /api/v1/admin/iqpe/review-queue
     */
    public function reviewQueue(Request $request): JsonResponse
    {
        $allowedFilters = [
            'all',
            'ready_for_approval',
            'requires_review',
            'with_official_key',
            'without_official_key',
            'discrepancies',
            'quality_issues',
            'duplicates',
            'approved',
            'rejected',
            'published',
        ];

        $filter = (string) $request->input('filter', 'all');
        if (! in_array($filter, $allowedFilters, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Filtro de revisión no válido.',
            ], 422);
        }

        $query = Question::with([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
            'topic',
            'sourceDocument',
        ])->where('is_current_version', true);

        if ($request->filled('subject_id') && is_numeric($request->input('subject_id'))) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        if ($request->filled('source_document_id')) {
            $docId = (string) $request->input('source_document_id');
            if (! Str::isUuid($docId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El identificador del documento fuente no es un UUID válido.',
                ], 422);
            }
            $query->where('source_document_id', $docId);
        }

        $questions = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('order_in_group', 'asc')
            ->get();

        $questionIds = $questions->pluck('id')->all();

        // Precargar artefactos IQPE en lotes para evitar consultas N+1
        $verificationsByQ = ! empty($questionIds)
            ? IqpeVerification::whereIn('question_id', $questionIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('question_id')
            : collect();

        $auditsByQ = ! empty($questionIds)
            ? IqpeQualityAudit::whereIn('question_id', $questionIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('question_id')
            : collect();

        $resolutionsByQ = ! empty($questionIds)
            ? IqpeQuestionResolution::whereIn('question_id', $questionIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('question_id')
            : collect();

        $decisionsByQ = ! empty($questionIds)
            ? IqpeAdminDecision::whereIn('question_id', $questionIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('question_id')
            : collect();

        $search = mb_strtolower(trim((string) $request->input('search', '')));

        $counts = [
            'all'                  => 0,
            'ready_for_approval'   => 0,
            'requires_review'      => 0,
            'with_official_key'    => 0,
            'without_official_key' => 0,
            'discrepancies'        => 0,
            'quality_issues'       => 0,
            'duplicates'           => 0,
            'approved'             => 0,
            'rejected'             => 0,
            'published'            => 0,
        ];

        $enrichedItems = [];

        foreach ($questions as $q) {
            if ($search !== '') {
                $haystack = mb_strtolower(
                    ($q->statement ?? '') . ' ' .
                    ($q->subject?->name ?? '') . ' ' .
                    ($q->topic?->name ?? '') . ' ' .
                    ($q->sourceDocument?->title ?? '')
                );
                if (! str_contains($haystack, $search)) {
                    continue;
                }
            }

            $verification = ($verificationsByQ->get($q->id) ?? collect())->first();
            $audit = ($auditsByQ->get($q->id) ?? collect())->first();
            $decisions = $decisionsByQ->get($q->id) ?? collect();

            $targetJobId = $verification?->job_id ?? $audit?->job_id;
            $qResAll = $resolutionsByQ->get($q->id) ?? collect();
            $qRes = ($targetJobId ? $qResAll->where('job_id', $targetJobId) : $qResAll)->keyBy('round_number');

            $eligibility = $this->workflowService->evaluateEligibility($q, $verification, $audit, $decisions, verifyStorage: false);

            $r1 = $qRes->get(1);
            $r2 = $qRes->get(2);
            $r3 = $qRes->get(3);

            $hasOfficialKey = $verification
                ? (bool) $verification->has_official_key
                : ($q->key_status === 'verified' && ! $eligibility['manual_key_assigned']);

            $hasDiscrepancy = $eligibility['has_discrepancy']
                || ($r1 && $r2 && $r1->solved_option_letter && $r2->solved_option_letter && $r1->solved_option_letter !== $r2->solved_option_letter)
                || ($r3 && $r1 && $r3->solved_option_letter && $r1->solved_option_letter && $r3->solved_option_letter !== $r1->solved_option_letter);

            $findingsCount = (int) ($audit?->audit_warnings['findings_count'] ?? 0);
            $hasQualityIssues = ($audit && ! $audit->passed_quality_check)
                || ! empty($q->extraction_warnings)
                || $findingsCount > 0;

            $dupClass = $audit?->audit_warnings['duplicate_assessment']['classification'] ?? 'independent';
            $hasDuplicate = (bool) ($audit?->duplicate_risk_flag ?? false)
                || in_array($dupClass, ['exact_duplicate', 'probable_duplicate'], true);

            $isReadyForApproval = $q->status === 'draft'
                && $eligibility['can_approve']
                && ! $hasDiscrepancy
                && ! $hasDuplicate
                && ($audit === null || $audit->passed_quality_check);

            $requiresReview = $q->status === 'draft'
                && ($hasDiscrepancy || $hasQualityIssues || $hasDuplicate || ! $eligibility['can_approve'] || ! $hasOfficialKey || ($verification?->requires_admin_review ?? false));

            $counts['all']++;
            if ($isReadyForApproval) {
                $counts['ready_for_approval']++;
            }
            if ($requiresReview) {
                $counts['requires_review']++;
            }
            if ($hasOfficialKey) {
                $counts['with_official_key']++;
            } else {
                $counts['without_official_key']++;
            }
            if ($hasDiscrepancy) {
                $counts['discrepancies']++;
            }
            if ($hasQualityIssues) {
                $counts['quality_issues']++;
            }
            if ($hasDuplicate) {
                $counts['duplicates']++;
            }
            if ($q->status === 'review') {
                $counts['approved']++;
            }
            if ($q->status === 'archived') {
                $counts['rejected']++;
            }
            if ($q->status === 'published') {
                $counts['published']++;
            }

            $matchesFilter = match ($filter) {
                'ready_for_approval'   => $isReadyForApproval,
                'requires_review'      => $requiresReview,
                'with_official_key'    => $hasOfficialKey,
                'without_official_key' => ! $hasOfficialKey,
                'discrepancies'        => $hasDiscrepancy,
                'quality_issues'       => $hasQualityIssues,
                'duplicates'           => $hasDuplicate,
                'approved'             => $q->status === 'review',
                'rejected'             => $q->status === 'archived',
                'published'            => $q->status === 'published',
                default                => true,
            };

            if (! $matchesFilter) {
                continue;
            }

            $markedOpt = $q->options->firstWhere('is_correct', true);
            $currentMarked = $markedOpt ? strtoupper(trim((string) $markedOpt->option_letter)) : null;
            $candidateAnswer = $verification?->official_key_letter
                ?: $currentMarked
                ?: ($r1?->solved_option_letter ?: $r2?->solved_option_letter ?: $r3?->solved_option_letter);

            $topMatch = $audit?->audit_warnings['duplicate_assessment']['matches'][0] ?? null;
            $latestDecision = $decisions->first();

            $item = $q->toArray();
            $item['iqpe_summary'] = [
                'job_id'             => $targetJobId,
                'candidate_answer'   => $candidateAnswer,
                'resolutions_summary' => [
                    'r1_letter' => $r1?->solved_option_letter,
                    'r1_status' => $r1?->status,
                    'r2_letter' => $r2?->solved_option_letter,
                    'r2_status' => $r2?->status,
                    'r3_letter' => $r3?->solved_option_letter,
                    'r3_status' => $r3?->status,
                ],
                'official_key' => [
                    'has_official_key'      => $hasOfficialKey,
                    'official_key_letter'   => $verification?->official_key_letter ?: ($hasOfficialKey ? $currentMarked : null),
                    'current_marked_letter' => $currentMarked,
                    'manual_key_assigned'   => $eligibility['manual_key_assigned'],
                ],
                'verification_status' => $verification ? [
                    'verdict'               => $verification->verdict,
                    'recommended_status'    => $verification->recommended_status,
                    'requires_admin_review' => $verification->requires_admin_review,
                    'is_approved_by_admin'  => $verification->is_approved_by_admin,
                    'confidence_index'      => $verification->confidence_index,
                    'discrepancy_details'   => $verification->discrepancy_details,
                ] : null,
                'quality_audit_status' => $audit ? [
                    'passed_quality_check'   => $audit->passed_quality_check,
                    'overall_classification' => $audit->audit_warnings['overall_classification'] ?? 'requires_review',
                    'highest_severity'       => $audit->audit_warnings['highest_severity'] ?? 'none',
                    'findings_count'         => $findingsCount,
                    'media_integrity_status' => $audit->media_integrity_status,
                ] : null,
                'duplicate_status' => [
                    'duplicate_risk_flag'         => $hasDuplicate,
                    'classification'              => $dupClass,
                    'duplicate_candidate_id'      => $audit?->duplicate_candidate_id,
                    'involves_published_question' => (bool) ($audit?->audit_warnings['duplicate_assessment']['involves_published_question'] ?? false),
                    'top_similarity'              => $topMatch['similarity_score'] ?? null,
                ],
                'latest_decision' => $latestDecision ? [
                    'action'     => $latestDecision->action,
                    'admin_name' => $latestDecision->admin_name,
                    'reason'     => $latestDecision->reason,
                    'notes'      => $latestDecision->notes,
                    'created_at' => $latestDecision->created_at?->toIso8601String(),
                ] : null,
                'flags' => [
                    'is_ready_for_approval' => $isReadyForApproval,
                    'requires_review'       => $requiresReview,
                    'has_discrepancy'       => $hasDiscrepancy,
                    'has_quality_issues'    => $hasQualityIssues,
                    'has_duplicate'         => $hasDuplicate,
                ],
                'eligibility' => $eligibility,
            ];

            $enrichedItems[] = $item;
        }

        $total = count($enrichedItems);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page = max(1, (int) $request->input('page', 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $pagedItems = array_slice($enrichedItems, $offset, $perPage);

        return response()->json([
            'success'       => true,
            'data'          => array_values($pagedItems),
            'meta'          => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $total > 0 ? $offset + 1 : null,
                'to'           => $total > 0 ? $offset + count($pagedItems) : null,
            ],
            'filter_counts' => $counts,
        ]);
    }

    /**
     * Consulta el detalle completo de revisión IQPE de una pregunta (R1, R2, R3, clave oficial,
     * verificación, auditoría, duplicados comparativos y trazabilidad administrativa).
     *
     * GET /api/v1/admin/questions/{id}/iqpe-detail
     */
    public function detail(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de pregunta no válido.',
            ], 422);
        }

        $question = Question::with([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
            'topic',
            'sourceDocument',
        ])->find($id);

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->workflowService->getQuestionDetail($question),
        ]);
    }

    /**
     * Devuelve una pregunta aprobada o rechazada a revisión/corrección sin perder su historial.
     *
     * POST /api/v1/admin/questions/{id}/return-to-review
     */
    public function returnToReview(Request $request, string $id): JsonResponse
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
            'reason.required' => 'Debe indicar el motivo por el cual se devuelve la pregunta a revisión.',
            'reason.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $question = Question::find($id);
        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        $admin = $request->attributes->get('auth_admin', []);
        $updated = $this->workflowService->returnToReview(
            $question,
            $admin,
            $validated['reason'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'La pregunta fue devuelta a revisión/corrección conservando todas las resoluciones y auditorías.',
            'data'    => $updated,
        ]);
    }

    /**
     * Publica explícitamente una pregunta aprobada en el banco oficial para estudiantes ('published').
     * Acción independiente de la aprobación.
     *
     * POST /api/v1/admin/questions/{id}/publish
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de pregunta no válido.',
            ], 422);
        }

        $validated = $request->validate([
            'notes'                     => ['nullable', 'string', 'max:2000'],
            'confirm_duplicate_publish' => ['nullable', 'boolean'],
        ]);

        $question = Question::find($id);
        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'Pregunta no encontrada.',
            ], 404);
        }

        $admin = $request->attributes->get('auth_admin', []);
        $published = $this->workflowService->publishQuestion($question, $admin, $validated);

        return response()->json([
            'success' => true,
            'message' => '¡Pregunta publicada exitosamente en el banco oficial para estudiantes!',
            'data'    => $published,
        ]);
    }
}

