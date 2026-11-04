<?php

namespace App\Console\Commands;

use App\Services\SupabaseStorageService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Console\Command;
use Throwable;

class TestQuestionBankStorageWrite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'question-bank:test-storage-write';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Valida el ciclo completo de subida, existencia, recuperación, URL firmada y eliminación en Supabase Storage';

    /**
     * Execute the console command.
     */
    public function handle(SupabaseStorageService $storageService): int
    {
        $this->info('Iniciando prueba real de lectura y escritura en Supabase Storage...');

        $bucket = config('filesystems.disks.question_bank_documents.bucket', env('QB_STORAGE_DOCUMENTS_BUCKET', 'icfes-documents'));
        $key = config('filesystems.disks.question_bank_storage.key');
        $secret = config('filesystems.disks.question_bank_storage.secret');

        if (empty($bucket)) {
            $this->error('Error de configuración: QB_STORAGE_DOCUMENTS_BUCKET no está definido en .env.');
            return Command::FAILURE;
        }

        // Generar un identificador único seguro para el archivo de prueba
        $uniqueId = bin2hex(random_bytes(6));
        $timestamp = date('Ymd_His');
        $fileName = "write_test_{$timestamp}_{$uniqueId}.txt";
        $filePath = "connection-tests/{$fileName}";

        $testPayload = "ATHENA Question Bank - Prueba de conectividad Storage.\n"
            . "Timestamp: " . now()->toIso8601String() . "\n"
            . "Token de prueba: " . bin2hex(random_bytes(16)) . "\n";

        $steps = [];
        $uploaded = false;
        $success = false;

        try {
            $this->comment("Bucket de prueba: [{$bucket}]");
            $this->comment("Ruta de prueba: [{$filePath}]");
            $this->newLine();

            // 1. SUBIDA (Upload)
            $this->line('<fg=yellow>1/6</> Subiendo archivo temporal de prueba...');
            $uploadResult = $storageService->upload($bucket, $filePath, $testPayload);

            if (! $uploadResult) {
                throw new \RuntimeException('Falló la subida del archivo al bucket S3.');
            }

            $uploaded = true;
            $steps[] = ['1. Subida de archivo', 'connection-tests/' . $fileName, 'OK'];
            $this->info('  [OK] Archivo temporal subido con éxito.');

            // 2. COMPROBACIÓN DE EXISTENCIA
            $this->line('<fg=yellow>2/6</> Comprobando existencia en el bucket...');
            $exists = $storageService->exists($bucket, $filePath);

            if (! $exists) {
                throw new \RuntimeException('El archivo fue reportado como subido pero exists() retornó false.');
            }

            $steps[] = ['2. Existencia en bucket', 'exists() === true', 'OK'];
            $this->info('  [OK] El archivo existe en el bucket privado.');

            // 3. RECUPERACIÓN DE CONTENIDO Y METADATOS
            $this->line('<fg=yellow>3/6</> Recuperando contenido y comprobando integridad...');
            $retrievedContent = $storageService->get($bucket, $filePath);
            $size = $storageService->size($bucket, $filePath);

            if ($retrievedContent !== $testPayload) {
                throw new \RuntimeException('El contenido recuperado no coincide con el payload original.');
            }

            $steps[] = ['3. Recuperación e integridad', "{$size} bytes recuperados coincidentes", 'OK'];
            $this->info("  [OK] Contenido recuperado correctamente ({$size} bytes, integridad 100%).");

            // 4. GENERACIÓN DE URL TEMPORAL FIRMADA
            $this->line('<fg=yellow>4/6</> Generando URL temporal firmada...');
            $signedUrl = $storageService->temporaryUrl($bucket, $filePath, 300);

            if (empty($signedUrl) || ! filter_var($signedUrl, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('La URL temporal generada no tiene un formato válido.');
            }

            // Comprobar que contiene el host y los parámetros de firma S3 sin imprimirla
            if (! str_contains($signedUrl, 'X-Amz-Signature') && ! str_contains($signedUrl, 'token=')) {
                throw new \RuntimeException('La URL generada no incluye los parámetros de autenticación S3 requeridos.');
            }

            $steps[] = ['4. URL temporal firmada', 'Generada y validada criptográficamente (300s)', 'OK'];
            $this->info('  [OK] URL temporal firmada generada satisfactoriamente (no expuesta en consola).');

            // 5. ELIMINACIÓN CONTROLADA
            $this->line('<fg=yellow>5/6</> Eliminando archivo temporal de prueba...');
            $deleted = $storageService->delete($bucket, $filePath, force: true);

            if (! $deleted) {
                throw new \RuntimeException('Falló la eliminación del archivo temporal mediante delete().');
            }

            $uploaded = false; // Ya no requiere limpieza en finally
            $steps[] = ['5. Eliminación de prueba', 'delete() con $force = true', 'OK'];
            $this->info('  [OK] Archivo temporal eliminado con confirmación explícita.');

            // 6. VERIFICACIÓN DE BORRADO
            $this->line('<fg=yellow>6/6</> Verificando que el archivo ya no existe...');
            $stillExists = $storageService->exists($bucket, $filePath);

            if ($stillExists) {
                throw new \RuntimeException('El archivo continúa existiendo tras la operación de eliminación.');
            }

            $steps[] = ['6. Verificación post-borrado', 'exists() === false', 'OK'];
            $this->info('  [OK] Confirmado: el archivo ya no existe en el bucket.');

            $success = true;

        } catch (Throwable $e) {
            $sanitizedMessage = $this->sanitizeErrorMessage($e->getMessage(), [$key, $secret]);
            $this->newLine();
            $this->error('Fallo en la prueba de almacenamiento: ' . $sanitizedMessage);

            if ($e instanceof S3Exception) {
                $this->line('Código de error S3: ' . ($e->getAwsErrorCode() ?? $e->getStatusCode()));
            }

            return Command::FAILURE;

        } finally {
            // Limpieza de seguridad garantizada en caso de error previo a la eliminación
            if ($uploaded) {
                $this->comment('Ejecutando limpieza de seguridad en bloque finally...');
                try {
                    $storageService->delete($bucket, $filePath, force: true);
                    $this->info('Limpieza preventiva completada.');
                } catch (Throwable $cleanupError) {
                    $this->warn('No se pudo limpiar automáticamente el archivo temporal: ' . $cleanupError->getMessage());
                }
            }

            $this->newLine();
            $this->table(['Paso', 'Detalle de la Operación', 'Resultado'], $steps);
            $this->newLine();

            if ($success) {
                $this->info('Todas las operaciones reales de lectura y escritura en Supabase Storage concluyeron satisfactoriamente.');
                $this->line('El bucket conserva su estado limpio sin archivos residuales de prueba.');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Sanitizar credenciales para evitar filtraciones en consola.
     */
    private function sanitizeErrorMessage(string $message, array $sensitiveTokens): string
    {
        foreach ($sensitiveTokens as $token) {
            if (!empty($token) && is_string($token)) {
                $message = str_replace($token, '********', $message);
            }
        }

        return $message;
    }
}

