<?php

namespace App\Jobs;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIqpeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos antes de marcar el trabajo como definitivamente fallido.
     */
    public int $tries = 2;

    /**
     * Tiempo límite de ejecución en segundos (10 minutos para documentos extensos).
     */
    public int $timeout = 600;

    /**
     * Tiempo de espera en segundos antes de un reintento controlado.
     */
    public int $backoff = 30;

    public function __construct(
        public string $documentId,
        public string $jobId
    ) {}

    /**
     * Ejecuta el trabajo de extracción y procesamiento asíncrono IQPE.
     */
    public function handle(GeminiQuestionExtractionService $extractionService): void
    {
        Log::info("Iniciando ProcessIqpeDocumentJob para Documento: {$this->documentId}, Job: {$this->jobId}");

        $iqpeJob = IqpeDocumentJob::find($this->jobId);
        if (! $iqpeJob) {
            Log::error("ProcessIqpeDocumentJob abortado: No existe el registro IqpeDocumentJob {$this->jobId}");
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
            ->where('started_at', '>=', now()->subMinutes(15))
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

        // 2. Transición a estado 'extracting' y registro de inicio
        $startTime = now();
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

        try {
            // 3. Etapa de Extracción de Imágenes y figuras incrustadas
            IqpeExecutionLog::record(
                $this->jobId,
                'image_extraction',
                'info',
                'Iniciando inspección y extracción física de figuras incrustadas en el PDF...'
            );

            // 4. Etapa de Extracción Multimodal con Gemini
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

            IqpeExecutionLog::record(
                $this->jobId,
                'question_extraction',
                'info',
                "Extracción exitosa: {$detectedCount} preguntas, {$stimuliCount} estímulos y {$imagesCount} figuras detectadas.",
                ['summary' => $summary]
            );

            // 5. Finalización exitosa de la etapa de extracción
            $duration = max(1, (int) now()->diffInSeconds($startTime));

            $iqpeJob->update([
                'status'                   => 'completed',
                'total_questions_detected' => $detectedCount,
                'completed_at'             => now(),
                'duration_seconds'         => $duration,
            ]);

            $document->update([
                'status' => 'extracted',
            ]);

            IqpeExecutionLog::record(
                $this->jobId,
                'completed',
                'info',
                "Trabajo IQPE #{$iqpeJob->run_number} completado exitosamente en {$duration} segundos. {$detectedCount} preguntas guardadas como borradores."
            );

        } catch (Throwable $e) {
            $duration = max(1, (int) now()->diffInSeconds($startTime));
            $attempt = $this->attempts();

            Log::error("Error en ProcessIqpeDocumentJob (intento {$attempt}/{$this->tries}): " . $e->getMessage(), [
                'document_id' => $this->documentId,
                'job_id'      => $this->jobId,
                'trace'       => $e->getTraceAsString(),
            ]);

            IqpeExecutionLog::record(
                $this->jobId,
                'question_extraction',
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

            $document->update([
                'status' => 'failed',
            ]);

            // Si aún quedan intentos configurados, liberar con retardo controlado
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

            // Si los intentos se agotaron, lanzar la excepción para que Laravel registre la falla final
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
            if ($document && $document->status !== 'extracted') {
                $document->update(['status' => 'failed']);
            }
        } catch (Throwable) {
            // Ignorar errores secundarios en el manejador de fallos
        }
    }
}

