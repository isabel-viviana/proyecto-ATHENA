<?php

namespace App\Services;

use DateTimeInterface;
use DateInterval;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class SupabaseStorageService
{
    /**
     * Obtener la instancia del disco Filesystem asociada a un bucket.
     */
    public function getDiskForBucket(string $bucket): Filesystem
    {
        $docBucket = config('filesystems.disks.question_bank_documents.bucket', env('QB_STORAGE_DOCUMENTS_BUCKET', 'icfes-documents'));
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', env('QB_STORAGE_MEDIA_BUCKET', 'icfes-media'));

        if ($bucket === $docBucket || $bucket === 'documents') {
            return Storage::disk('question_bank_documents');
        }

        if ($bucket === $mediaBucket || $bucket === 'media') {
            return Storage::disk('question_bank_media');
        }

        // Bucket dinámico o personalizado
        return Storage::build([
            'driver' => 's3',
            'key' => config('filesystems.disks.question_bank_storage.key'),
            'secret' => config('filesystems.disks.question_bank_storage.secret'),
            'region' => config('filesystems.disks.question_bank_storage.region', 'us-east-1'),
            'bucket' => $bucket,
            'endpoint' => config('filesystems.disks.question_bank_storage.endpoint'),
            'use_path_style_endpoint' => config('filesystems.disks.question_bank_storage.use_path_style_endpoint', true),
            'visibility' => 'private',
            'throw' => true,
        ]);
    }

    /**
     * Subir archivo al bucket especificado.
     *
     * @param string $bucket Nombre o alias del bucket
     * @param string $path Ruta de destino dentro del bucket
     * @param mixed $contents Contenido en texto, recurso o archivo
     * @param array $options Opciones adicionales
     */
    public function upload(string $bucket, string $path, mixed $contents, array $options = []): bool
    {
        $options = array_merge(['visibility' => 'private'], $options);
        return $this->getDiskForBucket($bucket)->put($path, $contents, $options);
    }

    /**
     * Consultar si un archivo existe en el bucket.
     */
    public function exists(string $bucket, string $path): bool
    {
        return $this->getDiskForBucket($bucket)->exists($path);
    }

    /**
     * Obtener el contenido de un archivo.
     */
    public function get(string $bucket, string $path): ?string
    {
        return $this->getDiskForBucket($bucket)->get($path);
    }

    /**
     * Obtener el tamaño en bytes de un archivo.
     */
    public function size(string $bucket, string $path): int
    {
        return $this->getDiskForBucket($bucket)->size($path);
    }

    /**
     * Obtener el tipo MIME de un archivo.
     */
    public function mimeType(string $bucket, string $path): ?string
    {
        return $this->getDiskForBucket($bucket)->mimeType($path);
    }

    /**
     * Obtener una URL temporal firmada para acceso a archivos en buckets privados.
     *
     * @param string $bucket Nombre o alias del bucket
     * @param string $path Ruta del archivo dentro del bucket
     * @param DateTimeInterface|DateInterval|int $expiration Tiempo de validez (segundos o DateTime)
     * @param array $options Opciones para el generador S3
     */
    public function temporaryUrl(string $bucket, string $path, DateTimeInterface|DateInterval|int $expiration = 3600, array $options = []): string
    {
        if (is_int($expiration)) {
            $expiration = now()->addSeconds($expiration);
        }

        return $this->getDiskForBucket($bucket)->temporaryUrl($path, $expiration, $options);
    }

    /**
     * Eliminar un archivo únicamente cuando se solicite expresamente ($force = true).
     *
     * @param string $bucket Nombre o alias del bucket
     * @param string $path Ruta del archivo
     * @param bool $force Confirmación explícita de eliminación
     * @throws InvalidArgumentException
     */
    public function delete(string $bucket, string $path, bool $force = false): bool
    {
        if (! $force) {
            throw new InvalidArgumentException(
                'Operación de eliminación rechazada: no se proporcionó la confirmación explícita requerida ($force = true).'
            );
        }

        return $this->getDiskForBucket($bucket)->delete($path);
    }

    /**
     * Listar archivos del bucket sin modificarlos.
     */
    public function listFiles(string $bucket, string $directory = ''): array
    {
        return $this->getDiskForBucket($bucket)->files($directory);
    }
}

