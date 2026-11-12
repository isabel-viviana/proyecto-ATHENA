<?php

namespace App\Jobs;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
use App\Services\GeminiQuestionSolverService;
use App\Services\IqpeQualityAuditService;
use App\Services\IqpeVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessIqpeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos antes de marcar el trabajo como definitivamente fallido.
     */
    public int $tries = 2;

    /**
     * Tiempo límite de ejecución en segundos (15 minutos para cuadernillos de 50+ preguntas).
     */
    public int $timeout = 900;

    /**
     * Tiempo de espera en segundos antes de un reintento controlado.
     */
    public int $backoff = 30;

    public function __construct(
        public string $documentId,
        public string $jobId,
        public bool $skipExtraction = false
    ) {}

    /**
     * Ejecuta el flujo asíncrono IQPE:
     * 1. Extracción PDF -> borrador (se omite si ya fue extraído al reintentar resoluciones).
     * 2. Solver R1 independiente.
     * 3. Solver R2 independiente (contexto aislado).
     * 4. Verificación inicial (detección de clave oficial vs ausencia de clave).
     * 5. Solver R3 independiente (ÚNICAMENTE para preguntas sin clave oficial).
     * 6. Comparación final y persistencia de verificaciones en iqpe_verifications.
     * 7. Auditoría de calidad y detección de duplicados en iqpe_quality_audits.
     */
    public function handle(
        GeminiQuestionExtractionService $extractionService,
        GeminiQuestionSolverService $solverService,
        ?IqpeVerificationService $verificationService = null,
        ?IqpeQualityAuditService $qualityAuditService = null
    ): void {
        @set_time_limit(0);
        $verificationService ??= app(IqpeVerificationService::class);
        $qualityAuditService ??= app(IqpeQualityAuditService::class);
        Log::info("Iniciando ProcessIqpeDocumentJob para Documento: {$this->documentId}, Job: {$this->jobId}");

        $iqpeJob = IqpeDocumentJob::find($this->jobId);
        if (! $iqpeJob) {
            Log::error("ProcessIqpeDocumentJob abortado: No existe el registro IqpeDocumentJob {$this->jobId}");
            return;
        }

        if ($iqpeJob->status === 'completed') {
            Log::info("ProcessIqpeDocumentJob omitido: el trabajo {$this->jobId} ya figura como completado.");
            return;
        }

        $document = SourceDocument::find($this->documentId);
        if (! $document) {
            $iqpeJob->update([
                'status'       => 'failed',
                'completed_at' => now(),
                'error_log'    => 'Documento fuente no encontrado en la base de datos.',
            ]);
            IqpeExecutionLog::record(
                $this->jobId,
                'pdf_ingestion',
                'error',
                'Documento fuente no encontrado en la base de datos.'
            );
            return;
        }

        // 1. Prevención estricta de ejecuciones simultáneas duplicadas para el mismo documento
        $activeJob = IqpeDocumentJob::where('source_document_id', $this->documentId)
            ->where('id', '!=', $this->jobId)
            ->whereIn('status', ['extracting', 'solving_round_1', 'solving_round_2', 'verifying', 'solving_round_3', 'arbitrating', 'auditing'])
            ->where('started_at', '>=', now()->subMinutes(16))
            ->first();

        if ($activeJob) {
            $msg = "Ejecución concurrente omitida: Ya existe el trabajo activo #{$activeJob->run_number} ({$activeJob->id}) para este documento.";
            IqpeExecutionLog::record(
                $this->jobId,
                'pdf_ingestion',
                'warning',
                $msg,
                ['active_job_id' => $activeJob->id]
            );
            $iqpeJob->update([
                'status'       => 'failed',
                'completed_at' => now(),
                'error_log'    => $msg,
            ]);
            return;
        }

        $startTime = $iqpeJob->started_at ?? now();
        $existingQuestionsCount = Question::where('source_document_id', $this->documentId)->count();
        $configSkipExtraction = (bool) ($iqpeJob->config['skip_extraction'] ?? false);

        $shouldSkipExtraction = ($this->skipExtraction || $configSkipExtraction || $document->status === 'extracted')
            && $existingQuestionsCount > 0;

        try {
            // =========================================================================
            // ETAPA 1: EXTRACCIÓN MULTIMODAL (Solo si aún no existen preguntas extraídas)
            // =========================================================================
            if ($shouldSkipExtraction) {
                $iqpeJob->update([
                    'started_at'               => $startTime,
                    'total_questions_detected' => $existingQuestionsCount,
                    'error_log'                => null,
                ]);

                IqpeExecutionLog::record(
                    $this->jobId,
                    'question_extraction',
                    'info',
                    "Extracción omitida: reutilizando {$existingQuestionsCount} preguntas ya persistidas en base de datos para reanudar resoluciones pendientes."
                );
            } else {
                $iqpeJob->update([
                    'status'     => 'extracting',
                    'started_at' => $startTime,
                    'error_log'  => null,
                ]);

                $document->update([
                    'status' => 'processing',
                ]);

                IqpeExecutionLog::record(
                    $this->jobId,
                    'pdf_ingestion',
                    'info',
                    "Inicio de extracción IQPE (Corrida #{$iqpeJob->run_number}) para el documento '{$document->title}'."
                );

                IqpeExecutionLog::record(
                    $this->jobId,
                    'image_extraction',
                    'info',
                    'Iniciando inspección y extracción física de figuras incrustadas en el PDF...'
                );

                IqpeExecutionLog::record(
                    $this->jobId,
                    'question_extraction',
                    'info',
                    'Invocando motor multimodal Gemini para extracción de preguntas, fórmulas y opciones...'
                );

                $summary = $extractionService->extractQuestionsFromDocument($document);

                $detectedCount = (int) ($summary['questions_extracted'] ?? 0);
                $imagesCount = (int) ($summary['images_detected_count'] ?? 0);
                $stimuliCount = (int) ($summary['stimuli_created'] ?? 0);

                $iqpeJob->update([
                    'total_questions_detected' => $detectedCount,
                ]);

                $document->update([
                    'status' => 'extracted',
                ]);

                IqpeExecutionLog::record(
                    $this->jobId,
                    'question_extraction',
                    'info',
                    "Extracción exitosa: {$detectedCount} preguntas, {$stimuliCount} estímulos y {$imagesCount} figuras detectadas.",
                    ['summary' => $summary]
                );
            }

            // =========================================================================
            // ETAPA 2: PREPARACIÓN DE REACTIVOS PERSISTIDOS Y SOLVER R1 INDEPENDIENTE
            // (Consulta explícitamente solo columnas académicas; nunca selecciona is_correct ni explanation)
            // =========================================================================
            $questions = Question::select([
                'id',
                'subject_id',
                'stimulus_id',
                'source_document_id',
                'order_in_group',
                'statement',
                'question_image_path',
                'source_page',
                'visual_transcription',
                'visual_description',
            ])
                ->with([
                    'options' => fn ($q) => $q->select([
                        'id',
                        'question_id',
                        'option_letter',
                        'option_text',
                        'option_image_path',
                    ])->orderBy('option_letter', 'asc'),
                    'stimulus.confirmedMediaAssets',
                    'stimulus.mediaAssets',
                    'confirmedMediaAssets',
                    'mediaAssets',
                    'subject',
                ])
                ->where('source_document_id', $this->documentId)
                ->orderBy('order_in_group', 'asc')
                ->get();

            if ($questions->isEmpty()) {
                throw new RuntimeException('No se encontraron preguntas persistidas en el documento para ejecutar los resolutores IQPE.');
            }

            $totalQuestions = $questions->count();
            $solverModel = $solverService->getModelName();

            $iqpeJob->update([
                'status'                   => 'solving_round_1',
                'total_questions_detected' => $totalQuestions,
            ]);

            IqpeExecutionLog::record(
                $this->jobId,
                'solver_r1',
                'info',
                "Iniciando Solver R1 independiente ({$totalQuestions} preguntas | modelo: {$solverModel})..."
            );

            $r1Stats = $solverService->solveBatchForJob($iqpeJob, $questions, roundNumber: 1);

            IqpeExecutionLog::record(
                $this->jobId,
                'solver_r1',
                $r1Stats['failed'] > 0 ? 'warning' : 'info',
                "Solver R1 finalizado: {$r1Stats['success']} resueltas, {$r1Stats['uncertain']} inciertas, {$r1Stats['failed']} fallidas (omitidas previas: {$r1Stats['skipped']}). Tokens P/C: {$r1Stats['total_prompt_tokens']}/{$r1Stats['total_completion_tokens']}.",
                $r1Stats
            );

            // =========================================================================
            // ETAPA 3: SOLVER R2 INDEPENDIENTE (EJECUCIÓN SEPARADA SIN CONTEXTO DE R1)
            // =========================================================================
            $iqpeJob->update([
                'status' => 'solving_round_2',
            ]);

            IqpeExecutionLog::record(
                $this->jobId,
                'solver_r2',
                'info',
                "Iniciando Solver R2 independiente ({$totalQuestions} preguntas | modelo: {$solverModel} | aislamiento estricto)..."
            );

            $r2Stats = $solverService->solveBatchForJob($iqpeJob, $questions, roundNumber: 2);

            IqpeExecutionLog::record(
                $this->jobId,
                'solver_r2',
                $r2Stats['failed'] > 0 ? 'warning' : 'info',
                "Solver R2 finalizado: {$r2Stats['success']} resueltas, {$r2Stats['uncertain']} inciertas, {$r2Stats['failed']} fallidas (omitidas previas: {$r2Stats['skipped']}). Tokens P/C: {$r2Stats['total_prompt_tokens']}/{$r2Stats['total_completion_tokens']}.",
                $r2Stats
            );

            // =========================================================================
            // ETAPA 4: VERIFICACIÓN INICIAL DE CLAVES OFICIALES (INTERNO EN LARAVEL)
            // =========================================================================
            $r1r2Failed = ($r1Stats['failed'] ?? 0) + ($r2Stats['failed'] ?? 0);
            if ($r1r2Failed > 0) {
                $this->markPartialFailureAndMaybeRelease(
                    $iqpeJob,
                    $document,
                    $startTime,
                    'solver_r2',
                    "Procesamiento parcial: {$r1r2Failed} resoluciones de R1/R2 presentaron errores técnicos de red/API. Las resoluciones exitosas fueron preservadas; reintente para completar únicamente las fallidas."
                );
                return;
            }

            $iqpeJob->update(['status' => 'verifying']);

            // Consulta completa restringida exclusivamente al verificador y auditor interno en Laravel.
            // Esta colección NUNCA se pasa a GeminiQuestionSolverService.
            $questionsWithKeys = Question::with([
                'options' => fn ($q) => $q->orderBy('option_letter', 'asc'),
                'stimulus.confirmedMediaAssets',
                'stimulus.mediaAssets',
                'confirmedMediaAssets',
                'mediaAssets',
                'subject',
            ])
                ->where('source_document_id', $this->documentId)
                ->orderBy('order_in_group', 'asc')
                ->get();

            $classification = $verificationService->classifyQuestionsForVerification($questionsWithKeys);
            $scenarioACount = count($classification['scenario_a_ids']);
            $scenarioBIds = $classification['scenario_b_ids'];
            $scenarioBCount = count($scenarioBIds);

            IqpeExecutionLog::record(
                $this->jobId,
                'verification',
                'info',
                "Clasificación de claves oficiales: {$scenarioACount} pregunta(s) con clave oficial/estado verificable (Escenario A, sin R3) y {$scenarioBCount} pregunta(s) sin clave oficial (Escenario B, requieren R3 independiente).",
                ['scenario_a_count' => $scenarioACount, 'scenario_b_count' => $scenarioBCount]
            );

            // =========================================================================
            // ETAPA 5: SOLVER R3 INDEPENDIENTE (ÚNICAMENTE PARA PREGUNTAS SIN CLAVE OFICIAL)
            // =========================================================================
            $r3Stats = [
                'total'                   => 0,
                'skipped'                 => 0,
                'success'                 => 0,
                'uncertain'               => 0,
                'failed'                  => 0,
                'total_prompt_tokens'     => 0,
                'total_completion_tokens' => 0,
            ];

            if ($scenarioBCount > 0) {
                $iqpeJob->update(['status' => 'solving_round_3']);

                // Se reutiliza la colección sanitizada ($questions), libre de is_correct, key_status y R1/R2
                $r3SanitizedQuestions = $questions->whereIn('id', $scenarioBIds)->values();

                IqpeExecutionLog::record(
                    $this->jobId,
                    'solver_r3',
                    'info',
                    "Iniciando Solver R3 independiente para {$scenarioBCount} pregunta(s) sin clave oficial (modelo: {$solverModel} | aislamiento estricto)..."
                );

                $r3Stats = $solverService->solveBatchForJob($iqpeJob, $r3SanitizedQuestions, roundNumber: 3);

                IqpeExecutionLog::record(
                    $this->jobId,
                    'solver_r3',
                    $r3Stats['failed'] > 0 ? 'warning' : 'info',
                    "Solver R3 finalizado: {$r3Stats['success']} resueltas, {$r3Stats['uncertain']} inciertas, {$r3Stats['failed']} fallidas (omitidas previas: {$r3Stats['skipped']}). Tokens P/C: {$r3Stats['total_prompt_tokens']}/{$r3Stats['total_completion_tokens']}.",
                    $r3Stats
                );
            }

            // =========================================================================
            // ETAPA 6: COMPARACIÓN FINAL Y CLASIFICACIÓN EN iqpe_verifications
            // =========================================================================
            $iqpeJob->update(['status' => 'verifying']);
            $verifyStats = $verificationService->verifyJobQuestions($iqpeJob, $questionsWithKeys, persistFailed: false);

            $r3Failed = (int) ($r3Stats['failed'] ?? 0);
            $pendingVerification = (int) ($verifyStats['pending_or_failed'] ?? 0);

            if ($r3Failed > 0 || $pendingVerification > 0) {
                $this->markPartialFailureAndMaybeRelease(
                    $iqpeJob,
                    $document,
                    $startTime,
                    'solver_r3',
                    "Verificación incompleta: {$r3Failed} resolución(es) R3 fallaron y {$pendingVerification} pregunta(s) permanecen pendientes de verificación. R1 y R2 completadas fueron preservadas; reintente para recuperar únicamente lo pendiente."
                );
                return;
            }

            // =========================================================================
            // ETAPA 7: AUDITORÍA INTEGRAL DE CALIDAD Y DETECCIÓN DE DUPLICADOS
            // =========================================================================
            $iqpeJob->update(['status' => 'auditing']);
            $auditStats = $qualityAuditService->auditJobQuestions($iqpeJob, $questionsWithKeys);

            // =========================================================================
            // ETAPA 8: CIERRE DE TRABAJO (SIN APROBACIÓN NI PUBLICACIÓN AUTOMÁTICA)
            // =========================================================================
            $duration = max(1, (int) (now()->timestamp - $startTime->timestamp));

            $iqpeJob->update([
                'status'                   => 'completed',
                'total_questions_detected' => $totalQuestions,
                'completed_at'             => now(),
                'duration_seconds'         => $duration,
                'error_log'                => null,
            ]);

            IqpeExecutionLog::record(
                $this->jobId,
                'completed',
                'info',
                "Trabajo IQPE #{$iqpeJob->run_number} completado en {$duration}s. Verificación: {$verifyStats['verified_candidate']} candidatas con clave, {$verifyStats['verified_candidate_no_official_key']} sin clave (R1=R2=R3). Calidad: {$auditStats['ready_for_approval_count']}/{$auditStats['total_audited']} listas, {$auditStats['duplicate_risk_count']} duplicados potenciales.",
                ['verification' => $verifyStats, 'quality_audit' => $auditStats]
            );

        } catch (Throwable $e) {
            $duration = max(1, (int) (now()->timestamp - $startTime->timestamp));
            $attempt = $this->attempts();

            Log::error("Error en ProcessIqpeDocumentJob (intento {$attempt}/{$this->tries}): " . $e->getMessage(), [
                'document_id' => $this->documentId,
                'job_id'      => $this->jobId,
                'trace'       => $e->getTraceAsString(),
            ]);

            $stageFailed = $shouldSkipExtraction ? 'solver_r1' : 'question_extraction';

            IqpeExecutionLog::record(
                $this->jobId,
                $stageFailed,
                'error',
                "Error en intento {$attempt}/{$this->tries}: " . $e->getMessage(),
                ['exception' => get_class($e)]
            );

            $iqpeJob->update([
                'status'           => 'failed',
                'completed_at'     => now(),
                'duration_seconds' => $duration,
                'error_log'        => $e->getMessage(),
            ]);

            // Si la extracción aún no había terminado, marcar documento failed; si ya estaba extracted, conservar extracted
            if (! $shouldSkipExtraction && $existingQuestionsCount === 0) {
                $document->update(['status' => 'failed']);
            }

            if ($attempt < $this->tries) {
                IqpeExecutionLog::record(
                    $this->jobId,
                    'pdf_ingestion',
                    'warning',
                    "Programando reintento controlado en {$this->backoff} segundos..."
                );
                $this->release($this->backoff);
                return;
            }

            throw $e;
        }
    }

    /**
     * Manejo ante falla definitiva de todos los intentos del trabajo.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning("ProcessIqpeDocumentJob falló definitivamente para Job {$this->jobId}: " . ($exception?->getMessage() ?? 'Error desconocido'));

        try {
            $iqpeJob = IqpeDocumentJob::find($this->jobId);
            if ($iqpeJob && $iqpeJob->status !== 'completed') {
                $iqpeJob->update([
                    'status'       => 'failed',
                    'completed_at' => now(),
                    'error_log'    => $exception?->getMessage() ?? 'Fallo definitivo tras agotar reintentos.',
                ]);

                IqpeExecutionLog::record(
                    $this->jobId,
                    'failed',
                    'error',
                    'Trabajo marcado definitivamente como fallido tras agotar los reintentos permitidos: ' . ($exception?->getMessage() ?? '')
                );
            }

            $document = SourceDocument::find($this->documentId);
            $hasQuestions = Question::where('source_document_id', $this->documentId)->exists();
            if ($document && ! $hasQuestions && $document->status !== 'extracted') {
                $document->update(['status' => 'failed']);
            }
        } catch (Throwable) {
            // Ignorar errores secundarios
        }
    }

    /**
     * Marca el trabajo como fallido conservando las resoluciones y verificaciones ya completadas
     * y manteniendo el documento en 'extracted' para reanudar sin re-extraer.
     */
    protected function markPartialFailureAndMaybeRelease(
        IqpeDocumentJob $iqpeJob,
        SourceDocument $document,
        \Carbon\CarbonInterface $startTime,
        string $stage,
        string $errMsg
    ): void {
        $duration = max(1, (int) (now()->timestamp - $startTime->timestamp));

        $iqpeJob->update([
            'status'           => 'failed',
            'completed_at'     => now(),
            'duration_seconds' => $duration,
            'error_log'        => $errMsg,
        ]);

        if ($document->status !== 'extracted') {
            $document->update(['status' => 'extracted']);
        }

        IqpeExecutionLog::record($this->jobId, $stage, 'error', $errMsg);

        if ($this->attempts() < $this->tries) {
            IqpeExecutionLog::record(
                $this->jobId,
                $stage,
                'warning',
                "Programando reintento automático de resoluciones fallidas en {$this->backoff} segundos..."
            );
            $this->release($this->backoff);
        }
    }
}
