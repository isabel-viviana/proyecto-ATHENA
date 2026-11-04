<?php

namespace App\Console\Commands;

use App\Services\SupabaseStorageService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Console\Command;
use Throwable;

class TestQuestionBankStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'question-bank:test-storage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprueba la conexión con Supabase Storage (S3) y verifica el acceso a los buckets icfes-documents e icfes-media';

    /**
     * Execute the console command.
     */
    public function handle(SupabaseStorageService $storageService): int
    {
        $this->info('Iniciando prueba de conexión con Supabase Storage (S3)...');

        $endpoint = config('filesystems.disks.question_bank_storage.endpoint');
        $key = config('filesystems.disks.question_bank_storage.key');
        $secret = config('filesystems.disks.question_bank_storage.secret');
        $docBucket = config('filesystems.disks.question_bank_documents.bucket');
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket');

        // 1. Verificación de variables requeridas
        if (empty($endpoint) || empty($key) || empty($secret) || empty($docBucket) || empty($mediaBucket)) {
            $this->error('Configuración incompleta: Faltan variables de entorno QB_STORAGE_* en el archivo .env.');
            $this->line('Verifique que QB_STORAGE_ENDPOINT, QB_STORAGE_ACCESS_KEY, QB_STORAGE_SECRET_KEY, QB_STORAGE_DOCUMENTS_BUCKET y QB_STORAGE_MEDIA_BUCKET estén configuradas.');
            return Command::FAILURE;
        }

        $bucketsToTest = [
            'Documentos' => $docBucket,
            'Medios / Imágenes' => $mediaBucket,
        ];

        $results = [];
        $hasErrors = false;

        $this->comment('Verificando acceso a los buckets privados...');

        foreach ($bucketsToTest as $label => $bucketName) {
            try {
                // Operación estrictamente de solo lectura: listar contenido de la raíz del bucket
                $files = $storageService->listFiles($bucketName);
                $fileCount = count($files);

                $results[] = [
                    'Categoría' => $label,
                    'Bucket' => $bucketName,
                    'Estado' => 'Accesible (Lectura OK)',
                    'Archivos' => "{$fileCount} objeto(s)",
                ];
            } catch (Throwable $e) {
                $hasErrors = true;

                $sanitizedError = $this->sanitizeErrorMessage($e->getMessage(), [$key, $secret]);

                $results[] = [
                    'Categoría' => $label,
                    'Bucket' => $bucketName,
                    'Estado' => 'Fallo de acceso',
                    'Archivos' => 'Error: ' . $this->formatFriendlyError($e, $sanitizedError),
                ];
            }
        }

        $this->newLine();
        $this->table(['Categoría', 'Bucket', 'Estado de Conexión', 'Detalle'], $results);
        $this->newLine();

        if ($hasErrors) {
            $this->error('La verificación de Supabase Storage encontró errores en uno o más buckets.');
            $this->line('Sugerencias de diagnóstico:');
            $this->line('- Verifique que el endpoint S3 de Supabase sea correcto (debe incluir el path /storage/v1/s3).');
            $this->line('- Asegúrese de que las credenciales S3 (Access Key y Secret Key) correspondan a las generadas en Supabase Storage Settings.');
            $this->line('- Verifique que los buckets existan y conserven su configuración privada.');
            return Command::FAILURE;
        }

        $this->info('Prueba de Supabase Storage completada con éxito. Todos los buckets son accesibles.');
        $this->line('Nota de seguridad: No se realizó ninguna operación de escritura, alteración ni eliminación de archivos.');

        return Command::SUCCESS;
    }

    /**
     * Sanitizar cualquier credencial o clave privada del mensaje de error.
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

    /**
     * Producir un mensaje comprensible y legible.
     */
    private function formatFriendlyError(Throwable $e, string $sanitizedMessage): string
    {
        if ($e instanceof S3Exception) {
            $code = $e->getAwsErrorCode() ?? $e->getStatusCode();
            return "[S3 {$code}] " . ($e->getAwsErrorMessage() ?? 'Error en la petición S3');
        }

        // Resumen corto del error
        return mb_strimwidth($sanitizedMessage, 0, 90, '...');
    }
}

