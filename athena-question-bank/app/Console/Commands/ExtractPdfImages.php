<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\SourceDocument;
use App\Services\PdfImageExtractor;
use App\Services\SupabaseStorageService;
use Illuminate\Console\Command;

/**
 * Extrae las imágenes incrustadas de un PDF almacenado, las guarda en icfes-media
 * y las registra en media_assets con estado 'pending'.
 * No modifica preguntas, opciones ni estímulos existentes.
 */
class ExtractPdfImages extends Command
{
    protected $signature = 'qb:extract-pdf-images
        {document : UUID del source_document}
        {--dry-run : Solo detectar, sin subir ni registrar en BD}
        {--from-manifest : Registrar en media_assets desde manifest.json ya existente en Storage sin re-subir imágenes}';

    protected $description = 'Detecta y registra imágenes de un PDF en icfes-media y media_assets (estado pending)';

    public function handle(PdfImageExtractor $extractor, SupabaseStorageService $storage): int
    {
        $doc = SourceDocument::find($this->argument('document'));
        if (! $doc) {
            $this->error('Documento no encontrado.');
            return self::FAILURE;
        }

        $docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

        if ($this->option('from-manifest')) {
            return $this->registerFromExistingManifest($doc, $storage, $mediaBucket);
        }

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

        $registeredCount = 0;

        foreach ($result['images'] as $img) {
            $row = [
                'page'               => $img['page'],
                'pdf_object'         => $img['object'],
                'width'              => $img['width'],
                'height'             => $img['height'],
                'status'             => $img['status'],
                'storage_path'       => null,
                'association_status' => 'pending',
            ];

            if ($img['status'] === 'ok') {
                $path = sprintf('extracted/%s/page-%02d_obj%d.%s', $doc->id, $img['page'], $img['object'], $img['ext']);
                $row['storage_path'] = $path;
                $row['mime_type'] = $img['mime'];
                $row['sha256'] = hash('sha256', $img['bytes']);

                if (! $this->option('dry-run')) {
                    $storage->upload($mediaBucket, $path, $img['bytes'], ['ContentType' => $img['mime']]);

                    MediaAsset::firstOrCreate(
                        ['storage_path' => $path],
                        [
                            'source_document_id' => $doc->id,
                            'page_number'        => (int) $img['page'],
                            'width'              => (int) $img['width'],
                            'height'             => (int) $img['height'],
                            'mime_type'          => $img['mime'],
                            'sha256'             => $row['sha256'],
                            'association_status' => 'pending',
                        ]
                    );
                    $registeredCount++;
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
            $this->info("Imágenes y manifest.json guardados en {$mediaBucket}/extracted/{$doc->id}/ ({$registeredCount} registradas en media_assets).");
        } else {
            $this->warn('Dry-run: no se subió ni registró nada.');
        }

        return self::SUCCESS;
    }

    protected function registerFromExistingManifest(SourceDocument $doc, SupabaseStorageService $storage, string $mediaBucket): int
    {
        $manifestPath = "extracted/{$doc->id}/manifest.json";
        if (! $storage->exists($mediaBucket, $manifestPath)) {
            $this->error("No existe {$manifestPath} en el bucket {$mediaBucket}.");
            return self::FAILURE;
        }

        $manifest = json_decode((string) $storage->get($mediaBucket, $manifestPath), true);
        if (! is_array($manifest) || empty($manifest['images'])) {
            $this->error('El archivo manifest.json está vacío o es inválido.');
            return self::FAILURE;
        }

        $registered = 0;
        foreach ($manifest['images'] as $img) {
            if (($img['status'] ?? '') !== 'ok' || empty($img['storage_path'])) {
                continue;
            }

            $asset = MediaAsset::firstOrCreate(
                ['storage_path' => $img['storage_path']],
                [
                    'source_document_id' => $doc->id,
                    'page_number'        => (int) $img['page'],
                    'width'              => (int) $img['width'],
                    'height'             => (int) $img['height'],
                    'mime_type'          => (string) ($img['mime_type'] ?? 'image/jpeg'),
                    'sha256'             => (string) $img['sha256'],
                    'association_status' => 'pending',
                ]
            );

            $this->line(sprintf(
                ' - [%s] p.%d %dx%d %s (%s)',
                $asset->association_status,
                $asset->page_number,
                $asset->width,
                $asset->height,
                $asset->storage_path,
                $asset->id
            ));
            $registered++;
        }

        $this->info("Total recursos registrados/verificados en media_assets: {$registered}");
        return self::SUCCESS;
    }
}
