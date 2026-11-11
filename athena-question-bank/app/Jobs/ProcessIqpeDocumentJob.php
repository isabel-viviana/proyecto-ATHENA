<?php

namespace App\Jobs;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
use App\Services\GeminiQuestionSolverService;
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
     */
    public function handle(
        GeminiQuestionExtractionService $extractionService,
        GeminiQuestionSolverService $solverService
    ): void {
        @set_time_limit(0);
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
            ->whereIn('status', ['extracting', 'solving_round_1', 'solving_round_2', 'arbitrating', 'auditing'])
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
            // ETAPA 4: EVALUACIÓN FINAL DEL BLOQUE 3 (SIN ARBITRAJE NI PUBLICACIÓN)
            // =========================================================================
            $duration = max(1, (int) (now()->timestamp - $startTime->timestamp));
            $totalFailedResolutions = ($r1Stats['failed'] ?? 0) + ($r2Stats['failed'] ?? 0);

            if ($totalFailedResolutions > 0) {
                $errMsg = "Procesamiento parcial: {$totalFailedResolutions} resoluciones presentaron errores técnicos de red/API. Las resoluciones exitosas fueron preservadas; reintente para completar únicamente las fallidas.";

                $iqpeJob->update([
                    'status'           => 'failed',
                    'completed_at'     => now(),
                    'duration_seconds' => $duration,
                    'error_log'        => $errMsg,
                ]);

                // Conservar estado 'extracted' del documento para que el reintento no repita la extracción
                if ($document->status !== 'extracted') {
                    $document->update(['status' => 'extracted']);
                }

                IqpeExecutionLog::record(
                    $this->jobId,
                    'solver_r2',
                    'error',
                    $errMsg
                );

                if ($this->attempts() < $this->tries) {
                    IqpeExecutionLog::record(
                        $this->jobId,
                        'solver_r1',
                        'warning',
                        "Programando reintento automático de resoluciones fallidas en {$this->backoff} segundos..."
                    );
                    $this->release($this->backoff);
                }
                return;
            }

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
                "Trabajo IQPE #{$iqpeJob->run_number} completado en {$duration}s. {$totalQuestions} preguntas con R1 y R2 independientes listos para verificación pedagógica."
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
}
