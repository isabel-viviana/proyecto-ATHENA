<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIqpeDocumentJob;
use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
use App\Services\IqpeQualityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ExtractionController extends Controller
{
    public function __construct(
        protected GeminiQuestionExtractionService $extractionService
    ) {}

    /**
     * Encola el trabajo asíncrono de extracción mediante IQPE para el documento.
     *
     * POST /api/v1/admin/documents/{id}/extract
     */
    public function extract(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de documento no válido.',
            ], 422);
        }

        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Verificar si ya existe un trabajo activo
        $activeJob = IqpeDocumentJob::where('source_document_id', $id)
            ->whereIn('status', ['queued', 'extracting', 'solving_round_1', 'solving_round_2', 'verifying', 'solving_round_3', 'arbitrating', 'auditing'])
            ->where(function ($q) {
                $q->whereNull('started_at')
                  ->orWhere('started_at', '>=', now()->subMinutes(15));
            })
            ->first();

        if ($activeJob) {
            return response()->json([
                'success' => true,
                'message' => "Ya existe un trabajo IQPE en curso (Corrida #{$activeJob->run_number}) para este documento.",
                'data'    => [
                    'document_id' => $document->id,
                    'job_id'      => $activeJob->id,
                    'run_number'  => $activeJob->run_number,
                    'status'      => $activeJob->status,
                ],
            ]);
        }

        $maxRun = IqpeDocumentJob::where('source_document_id', $id)->max('run_number') ?? 0;
        $runNumber = $maxRun + 1;
        $jobUuid = (string) Str::uuid();

        $iqpeJob = IqpeDocumentJob::create([
            'id'                 => $jobUuid,
            'source_document_id' => $document->id,
            'run_number'         => $runNumber,
            'status'             => 'queued',
            'config'             => [
                'manual_dispatch' => true,
                'run_number'      => $runNumber,
            ],
        ]);

        $document->update(['status' => 'processing']);

        IqpeExecutionLog::record(
            $jobUuid,
            'pdf_ingestion',
            'info',
            "Trabajo IQPE #{$runNumber} encolado a petición del administrador."
        );

        ProcessIqpeDocumentJob::dispatch($document->id, $jobUuid);

        return response()->json([
            'success' => true,
            'message' => 'Procesamiento IQPE encolado exitosamente.',
            'data'    => [
                'document_id' => $document->id,
                'job_id'      => $jobUuid,
                'run_number'  => $runNumber,
                'status'      => 'queued',
            ],
        ]);
    }

    /**
     * Consulta el estado en tiempo real del orquestador IQPE para un documento.
     *
     * GET /api/v1/admin/documents/{id}/iqpe-status
     */
    public function iqpeStatus(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de documento no válido.',
            ], 422);
        }

        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $latestJob = IqpeDocumentJob::where('source_document_id', $id)
            ->orderBy('run_number', 'desc')
            ->first();

        $realQuestionsCount = Question::where('source_document_id', $id)->count();

        if (! $latestJob) {
            return response()->json([
                'success' => true,
                'data' => [
                    'document_id'              => $document->id,
                    'job_id'                   => null,
                    'run_number'               => 0,
                    'status'                   => $document->status ?? 'uploaded',
                    'current_stage'            => 'idle',
                    'progress'                 => 'Sin trabajos de procesamiento registrados.',
                    'total_questions_detected' => $realQuestionsCount,
                    'started_at'               => null,
                    'completed_at'             => null,
                    'duration_seconds'         => null,
                    'error_detail'             => null,
                    'recent_logs'              => [],
                ],
            ]);
        }

        $lastLog = IqpeExecutionLog::where('job_id', $latestJob->id)
            ->orderBy('id', 'desc')
            ->first();

        $currentStage = $lastLog?->stage ?? ($latestJob->status === 'queued' ? 'queued' : 'extraction');

        // Conteos reales de resoluciones completadas para reporte exacto de progreso
        $totalTarget = max(1, $latestJob->total_questions_detected ?: $realQuestionsCount);
        $r1Done = IqpeQuestionResolution::where('job_id', $latestJob->id)
            ->where('round_number', 1)
            ->whereIn('status', ['success', 'uncertain'])
            ->count();
        $r2Done = IqpeQuestionResolution::where('job_id', $latestJob->id)
            ->where('round_number', 2)
            ->whereIn('status', ['success', 'uncertain'])
            ->count();
        $r3Done = IqpeQuestionResolution::where('job_id', $latestJob->id)
            ->where('round_number', 3)
            ->whereIn('status', ['success', 'uncertain'])
            ->count();

        $verificationCounts = IqpeVerification::where('job_id', $latestJob->id)
            ->selectRaw("verdict, count(*) as total")
            ->groupBy('verdict')
            ->pluck('total', 'verdict');

        $verifiedCandidateCount = (int) ($verificationCounts['verified_candidate'] ?? 0);
        $verifiedNoKeyCount = (int) ($verificationCounts['verified_candidate_no_official_key'] ?? 0);
        $totalVerifications = (int) $verificationCounts->sum();
        $requiresReviewCount = max(0, $totalVerifications - $verifiedCandidateCount - $verifiedNoKeyCount);

        $auditRecords = IqpeQualityAudit::where('job_id', $latestJob->id)->get(['passed_quality_check', 'duplicate_risk_flag', 'audit_warnings']);
        $totalAudited = $auditRecords->count();
        $passedQualityCount = $auditRecords->where('passed_quality_check', true)->count();
        $duplicateRiskCount = $auditRecords->where('duplicate_risk_flag', true)->count();
        $criticalIssuesCount = $auditRecords->filter(function ($a) {
            $sev = $a->audit_warnings['highest_severity'] ?? 'none';
            $cls = $a->audit_warnings['overall_classification'] ?? '';
            return $sev === 'critical' || $cls === 'blocked_critical';
        })->count();

        $docQuestionsStatuses = Question::where('source_document_id', $id)
            ->where('is_current_version', true)
            ->get(['status']);

        // Derivar progreso real a partir del estado y etapa cronológica
        $progress = match ($latestJob->status) {
            'queued' => 'En cola de espera para asignación de worker.',
            'extracting' => match ($currentStage) {
                'pdf_ingestion'       => 'Validando archivo e ingiriendo páginas del cuadernillo...',
                'image_extraction'    => 'Extrayendo figuras, gráficas y tablas incrustadas en el PDF...',
                'question_extraction' => 'Invocando motor Gemini multimodal para extracción pedagógica...',
                default               => 'Extrayendo preguntas y recursos del documento...',
            },
            'solving_round_1' => "Ejecutando Solver R1 independiente ({$r1Done}/{$totalTarget} preguntas resueltas)...",
            'solving_round_2' => "Ejecutando Solver R2 independiente ({$r2Done}/{$totalTarget} preguntas resueltas)...",
            'verifying'       => "Verificando concordancia R1/R2 y claves oficiales ({$totalVerifications}/{$totalTarget})...",
            'solving_round_3' => "Ejecutando Solver R3 independiente para preguntas sin clave oficial ({$r3Done} resueltas)...",
            'arbitrating'     => 'Ejecutando arbitraje y desempate pedagógico Ronda 3...',
            'auditing'        => "Realizando auditoría de calidad y detección de duplicados ({$totalAudited}/{$totalTarget})...",
            'completed'       => "Procesamiento completado: {$verifiedCandidateCount} candidatas con clave, {$verifiedNoKeyCount} sin clave (R1=R2=R3), {$requiresReviewCount} en revisión. Calidad: {$passedQualityCount}/{$totalAudited} aprobables, {$duplicateRiskCount} duplicados potenciales.",
            'failed'          => 'Procesamiento fallido: ' . ($latestJob->error_log ?: 'Error no especificado.'),
            default           => ucfirst($latestJob->status),
        };

        $recentLogs = IqpeExecutionLog::where('job_id', $latestJob->id)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'stage', 'level', 'message', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'document_id'              => $document->id,
                'job_id'                   => $latestJob->id,
                'run_number'               => $latestJob->run_number,
                'status'                   => $latestJob->status,
                'current_stage'            => $currentStage,
                'progress'                 => $progress,
                'total_questions_detected' => $latestJob->total_questions_detected ?: $realQuestionsCount,
                'r1_completed_count'       => $r1Done,
                'r2_completed_count'       => $r2Done,
                'r3_completed_count'       => $r3Done,
                'verification_summary'     => [
                    'total_verified_records'               => $totalVerifications,
                    'verified_candidate_count'             => $verifiedCandidateCount,
                    'verified_candidate_no_official_count' => $verifiedNoKeyCount,
                    'requires_admin_review_count'          => $requiresReviewCount,
                ],
                'quality_audit_summary'    => [
                    'total_audited'             => $totalAudited,
                    'passed_quality_count'      => $passedQualityCount,
                    'requires_review_count'     => max(0, $totalAudited - $passedQualityCount),
                    'critical_issues_count'     => $criticalIssuesCount,
                    'duplicate_risk_count'      => $duplicateRiskCount,
                    'approved_questions_count'  => $docQuestionsStatuses->where('status', 'review')->count(),
                    'published_questions_count' => $docQuestionsStatuses->where('status', 'published')->count(),
                    'rejected_questions_count'  => $docQuestionsStatuses->where('status', 'archived')->count(),
                ],
                'started_at'               => $latestJob->started_at?->toIso8601String(),
                'completed_at'             => $latestJob->completed_at?->toIso8601String(),
                'duration_seconds'         => $latestJob->duration_seconds,
                'error_detail'             => $latestJob->error_log,
                'recent_logs'              => $recentLogs,
            ],
        ]);
    }

    /**
     * Reintenta o recupera el procesamiento de un documento fallido o interrumpido.
     * Si el documento ya tiene preguntas extraídas y no se solicita force_extract, omite la extracción del PDF.
     *
     * POST /api/v1/admin/documents/{id}/retry
     */
    public function retry(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de documento no válido.',
            ], 422);
        }

        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Prevenir reintento simultáneo si ya hay un trabajo activo
        $activeJob = IqpeDocumentJob::where('source_document_id', $id)
            ->whereIn('status', ['queued', 'extracting', 'solving_round_1', 'solving_round_2', 'verifying', 'solving_round_3', 'arbitrating', 'auditing'])
            ->where(function ($q) {
                $q->whereNull('started_at')
                  ->orWhere('started_at', '>=', now()->subMinutes(16));
            })
            ->first();

        if ($activeJob) {
            return response()->json([
                'success' => false,
                'message' => "Ya existe un trabajo en curso (#{$activeJob->run_number}) para este documento.",
                'data'    => [
                    'job_id'     => $activeJob->id,
                    'run_number' => $activeJob->run_number,
                    'status'     => $activeJob->status,
                ],
            ], 409);
        }

        $hasExtractedQuestions = Question::where('source_document_id', $id)->exists();
        $forceExtract = $request->boolean('force_extract', false);
        $skipExtraction = $hasExtractedQuestions && ! $forceExtract;

        $maxRun = IqpeDocumentJob::where('source_document_id', $id)->max('run_number') ?? 0;
        $runNumber = $maxRun + 1;
        $jobUuid = (string) Str::uuid();

        $iqpeJob = IqpeDocumentJob::create([
            'id'                 => $jobUuid,
            'source_document_id' => $document->id,
            'run_number'         => $runNumber,
            'status'             => 'queued',
            'config'             => [
                'retry'           => true,
                'skip_extraction' => $skipExtraction,
                'previous_runs'   => $maxRun,
                'dispatched_from' => 'api_retry',
            ],
        ]);

        if (! $skipExtraction) {
            $document->update(['status' => 'processing']);
        }

        IqpeExecutionLog::record(
            $jobUuid,
            $skipExtraction ? 'solver_r1' : 'pdf_ingestion',
            'info',
            $skipExtraction
                ? "Reintento #{$runNumber} encolado: omitiendo extracción PDF (preguntas ya persistidas) para ejecutar resoluciones R1 y R2."
                : "Reintento de procesamiento solicitado por el administrador. Trabajo IQPE #{$runNumber} encolado."
        );

        ProcessIqpeDocumentJob::dispatch($document->id, $jobUuid, $skipExtraction);

        return response()->json([
            'success' => true,
            'message' => "Reintento #{$runNumber} encolado exitosamente.",
            'data'    => [
                'document_id'     => $document->id,
                'job_id'          => $jobUuid,
                'run_number'      => $runNumber,
                'status'          => 'queued',
                'skip_extraction' => $skipExtraction,
            ],
        ]);
    }

    /**
     * Reanuda las resoluciones fallidas/pendientes sobre el último trabajo sin repetir extracción
     * ni sobrescribir las resoluciones ya completadas con éxito.
     *
     * POST /api/v1/admin/documents/{id}/retry-resolutions
     */
    public function retryResolutions(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de documento no válido.',
            ], 422);
        }

        $document = SourceDocument::find($id);
        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $latestJob = IqpeDocumentJob::where('source_document_id', $id)
            ->orderBy('run_number', 'desc')
            ->first();

        if (! $latestJob) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no tiene ningún trabajo IQPE registrado para reanudar.',
            ], 404);
        }

        if ($latestJob->isRunning() && $latestJob->started_at && $latestJob->started_at->gte(now()->subMinutes(16))) {
            return response()->json([
                'success' => false,
                'message' => "El trabajo #{$latestJob->run_number} ya se encuentra en ejecución.",
            ], 409);
        }

        if (! Question::where('source_document_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No existen preguntas extraídas en el documento; utilice /retry para ejecutar desde extracción.',
            ], 422);
        }

        $latestJob->update([
            'status'       => 'queued',
            'started_at'   => null,
            'completed_at' => null,
            'error_log'    => null,
        ]);

        IqpeExecutionLog::record(
            $latestJob->id,
            'verification',
            'info',
            "Reanudando resoluciones R1/R2/R3 y verificación pendientes sobre corrida #{$latestJob->run_number} (preservando rondas completadas previas)."
        );

        ProcessIqpeDocumentJob::dispatch($document->id, $latestJob->id, skipExtraction: true);

        return response()->json([
            'success' => true,
            'message' => "Reanudación de resoluciones encolada sobre la corrida #{$latestJob->run_number}.",
            'data'    => [
                'document_id'     => $document->id,
                'job_id'          => $latestJob->id,
                'run_number'      => $latestJob->run_number,
                'status'          => 'queued',
                'skip_extraction' => true,
            ],
        ]);
    }

    /**
     * Consulta el estado de procesamiento (mantiene retrocompatibilidad).
     *
     * GET /api/v1/admin/documents/{id}/extraction-status
     */
    public function status(string $id): JsonResponse
    {
        return $this->iqpeStatus($id);
    }

    /**
     * Consulta las preguntas y opciones extraídas vinculadas al documento.
     *
     * GET /api/v1/admin/documents/{id}/extracted-questions
     */
    public function questions(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de documento no válido.',
            ], 422);
        }

        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $questions = Question::with([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
            'topic',
        ])
            ->where('source_document_id', $id)
            ->orderBy('order_in_group', 'asc')
            ->get();

        $latestJob = IqpeDocumentJob::where('source_document_id', $id)
            ->orderBy('run_number', 'desc')
            ->first();

        $verificationsByQ = $latestJob
            ? IqpeVerification::where('job_id', $latestJob->id)->get()->keyBy('question_id')
            : collect();
        $auditsByQ = $latestJob
            ? IqpeQualityAudit::where('job_id', $latestJob->id)->get()->keyBy('question_id')
            : collect();
        $resolutionsByQ = $latestJob
            ? IqpeQuestionResolution::where('job_id', $latestJob->id)->get()->groupBy('question_id')
            : collect();

        $enriched = $questions->map(function (Question $q) use ($latestJob, $verificationsByQ, $auditsByQ, $resolutionsByQ) {
            $arr = $q->toArray();
            $v = $verificationsByQ->get($q->id);
            $a = $auditsByQ->get($q->id);
            $res = ($resolutionsByQ->get($q->id) ?? collect())->keyBy('round_number');

            $arr['iqpe_summary'] = $latestJob ? [
                'job_id' => $latestJob->id,
                'resolutions_summary' => [
                    'r1_letter' => $res->get(1)?->solved_option_letter,
                    'r1_status' => $res->get(1)?->status,
                    'r2_letter' => $res->get(2)?->solved_option_letter,
                    'r2_status' => $res->get(2)?->status,
                    'r3_letter' => $res->get(3)?->solved_option_letter,
                    'r3_status' => $res->get(3)?->status,
                ],
                'verification_status' => $v ? [
                    'verdict'               => $v->verdict,
                    'recommended_status'    => $v->recommended_status,
                    'requires_admin_review' => $v->requires_admin_review,
                    'is_approved_by_admin'  => $v->is_approved_by_admin,
                    'confidence_index'      => $v->confidence_index,
                    'discrepancy_details'   => $v->discrepancy_details,
                ] : null,
                'quality_audit_status' => $a ? [
                    'passed_quality_check'   => $a->passed_quality_check,
                    'overall_classification' => $a->audit_warnings['overall_classification'] ?? 'requires_review',
                    'highest_severity'       => $a->audit_warnings['highest_severity'] ?? 'none',
                    'findings_count'         => $a->audit_warnings['findings_count'] ?? 0,
                    'media_integrity_status' => $a->media_integrity_status,
                ] : null,
                'duplicate_status' => [
                    'duplicate_risk_flag'         => (bool) ($a?->duplicate_risk_flag ?? false),
                    'classification'              => $a?->audit_warnings['duplicate_assessment']['classification'] ?? 'independent',
                    'duplicate_candidate_id'      => $a?->duplicate_candidate_id,
                    'involves_published_question' => (bool) ($a?->audit_warnings['duplicate_assessment']['involves_published_question'] ?? false),
                ],
            ] : null;

            return $arr;
        })->values()->all();

        return response()->json([
            'success' => true,
            'data'    => [
                'document_id'     => $document->id,
                'title'           => $document->title,
                'total_questions' => count($enriched),
                'questions'       => $enriched,
            ],
        ]);
    }

    /**
     * Ejecuta o consulta la auditoría automática de calidad y detección de duplicados de un documento.
     *
     * GET/POST /api/v1/admin/documents/{id}/audit
     */
    public function audit(Request $request, string $id, IqpeQualityAuditService $qualityAuditService): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de documento no válido.',
            ], 422);
        }

        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $questions = Question::with([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
        ])
            ->where('source_document_id', $id)
            ->orderBy('order_in_group', 'asc')
            ->get();

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no tiene preguntas extraídas para auditar.',
            ], 422);
        }

        $latestJob = IqpeDocumentJob::where('source_document_id', $id)
            ->orderBy('run_number', 'desc')
            ->first();

        if ($request->isMethod('post') && $latestJob && ! $latestJob->isRunning()) {
            $qualityAuditService->auditJobQuestions($latestJob, $questions);
        }

        $qualityAudits = $latestJob
            ? IqpeQualityAudit::where('job_id', $latestJob->id)->get()
            : collect();

        $verified = $questions->where('key_status', 'verified')->count();
        $unverified = $questions->where('key_status', 'unverified')->count();
        $withWarnings = $questions->filter(fn($q) => !empty($q->extraction_warnings))->count();
        $failedQualityCount = $qualityAudits->where('passed_quality_check', false)->count();
        $duplicateRiskCount = $qualityAudits->where('duplicate_risk_flag', true)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'document_id'          => $document->id,
                'title'                => $document->title,
                'job_id'               => $latestJob?->id,
                'total_questions'      => $questions->count(),
                'verified_keys'        => $verified,
                'unverified_keys'      => $unverified,
                'with_warnings'        => $withWarnings,
                'duplicate_risk_count' => $duplicateRiskCount,
                'audit_status'         => ($withWarnings === 0 && $failedQualityCount === 0 && $duplicateRiskCount === 0) ? 'optimal' : 'requires_review',
                'iqpe_quality_audits'  => $qualityAudits,
            ],
        ]);
    }
}
