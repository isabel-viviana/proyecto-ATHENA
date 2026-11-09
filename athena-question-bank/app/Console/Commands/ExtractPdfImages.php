<?php

namespace App\Console\Commands;

use App\Models\SourceDocument;
use App\Services\PdfImageExtractor;
use App\Services\SupabaseStorageService;
use Illuminate\Console\Command;

/**
 * Extrae las imágenes incrustadas de un PDF almacenado y las guarda en icfes-media.
 * No modifica la base de datos: la relación imagen↔pregunta/estímulo queda PENDIENTE
 * de revisión administrativa (ver manifest.json generado junto a las imágenes).
 */
class ExtractPdfImages extends Command
{
    protected $signature = 'qb:extract-pdf-images {document : UUID del source_document} {--dry-run : Solo detectar, sin subir}';
    protected $description = 'Detecta y extrae imágenes de un PDF hacia el bucket icfes-media (sin tocar preguntas)';

    public function handle(PdfImageExtractor $extractor, SupabaseStorageService $storage): int
    {
        $doc = SourceDocument::find($this->argument('document'));
        if (! $doc) {
            $this->error('Documento no encontrado.');
            return self::FAILURE;
        }

        $docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

        $pdf = $storage->get($docBucket, $doc->storage_path);
        if (empty($pdf)) {
            $this->error('No se pudo leer el PDF desde Storage.');
            return self::FAILURE;
        }

        $result = $extractor->extract($pdf);
        $this->info("Páginas: {$result['pages']} | Imágenes relevantes detectadas: " . count($result['images']));

        $manifest = [
            'source_document_id' => $doc->id,
            'generated_at'       => now()->toIso8601String(),
            'total_pages'        => $result['pages'],
            'images'             => [],
        ];

        foreach ($result['images'] as $img) {
            $row = [
                'page'               => $img['page'],
                'pdf_object'         => $img['object'],
                'width'              => $img['width'],
                'height'             => $img['height'],
                'status'             => $img['status'],
                'storage_path'       => null,
                'association_status' => 'pending', // sin asociación segura
            ];

            if ($img['status'] === 'ok') {
                $path = sprintf('extracted/%s/page-%02d_obj%d.%s', $doc->id, $img['page'], $img['object'], $img['ext']);
                $row['storage_path'] = $path;
                $row['mime_type'] = $img['mime'];
                $row['sha256'] = hash('sha256', $img['bytes']);
                if (! $this->option('dry-run')) {
                    $storage->upload($mediaBucket, $path, $img['bytes'], ['ContentType' => $img['mime']]);
                }
            }

            $this->line(sprintf(
                ' - p.%d obj%d %dx%d [%s] %s',
                $img['page'], $img['object'], $img['width'], $img['height'], $img['status'], $row['storage_path'] ?? ''
            ));
            $manifest['images'][] = $row;
        }

        if (! $this->option('dry-run')) {
            $storage->upload(
                $mediaBucket,
                "extracted/{$doc->id}/manifest.json",
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ['ContentType' => 'application/json']
            );
            $this->info("Imágenes y manifest.json guardados en {$mediaBucket}/extracted/{$doc->id}/");
        } else {
            $this->warn('Dry-run: no se subió nada.');
        }

        return self::SUCCESS;
    }
}

