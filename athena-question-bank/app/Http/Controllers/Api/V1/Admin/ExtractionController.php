<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIqpeDocumentJob;
use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQuestionResolution;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
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
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Verificar si ya existe un trabajo activo
        $activeJob = IqpeDocumentJob::where('source_document_id', $id)
            ->whereIn('status', ['queued', 'extracting', 'solving_round_1', 'solving_round_2', 'arbitrating', 'auditing'])
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
            'arbitrating'     => 'Ejecutando arbitraje y desempate pedagógico Ronda 3...',
            'auditing'        => 'Realizando auditoría de integridad y detección de reactivos duplicados...',
            'completed'       => "Procesamiento completado exitosamente. {$latestJob->total_questions_detected} preguntas procesadas (R1: {$r1Done}, R2: {$r2Done}).",
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
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Prevenir reintento simultáneo si ya hay un trabajo activo
        $activeJob = IqpeDocumentJob::where('source_document_id', $id)
            ->whereIn('status', ['queued', 'extracting', 'solving_round_1', 'solving_round_2', 'arbitrating', 'auditing'])
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
            'solver_r1',
            'info',
            "Reanudando resoluciones R1/R2 fallidas o pendientes sobre corrida #{$latestJob->run_number} (preservando resoluciones exitosas previas)."
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

        return response()->json([
            'success' => true,
            'data'    => [
                'document_id'     => $document->id,
                'title'           => $document->title,
                'total_questions' => $questions->count(),
                'questions'       => $questions,
            ],
        ]);
    }

    /**
     * Ejecuta o consulta la auditoría automática de calidad de un documento.
     *
     * GET/POST /api/v1/admin/documents/{id}/audit
     */
    public function audit(string $id): JsonResponse
    {
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $questions = Question::with('options')
            ->where('source_document_id', $id)
            ->orderBy('order_in_group', 'asc')
            ->get();

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no tiene preguntas extraídas para auditar.',
            ], 422);
        }

        $verified = $questions->where('key_status', 'verified')->count();
        $unverified = $questions->where('key_status', 'unverified')->count();
        $withWarnings = $questions->filter(fn($q) => !empty($q->extraction_warnings))->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'document_id'       => $document->id,
                'title'             => $document->title,
                'total_questions'   => $questions->count(),
                'verified_keys'     => $verified,
                'unverified_keys'   => $unverified,
                'with_warnings'     => $withWarnings,
                'audit_status'      => $withWarnings === 0 ? 'optimal' : 'requires_review',
            ],
        ]);
    }
}
