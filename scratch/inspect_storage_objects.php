<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SupabaseStorageService;
use App\Models\SourceDocument;
use App\Models\MediaAsset;

$storage = app(SupabaseStorageService::class);
$docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

echo "=== DOCUMENTOS EN BASE DE DATOS ===" . PHP_EOL;
$docs = SourceDocument::all();
foreach ($docs as $d) {
    $exists = $storage->exists($docBucket, $d->storage_path);
    echo "Doc ID: {$d->id} | Titulo: {$d->title} | Path: {$d->storage_path} | Existe en Storage: " . ($exists ? 'SI' : 'NO') . PHP_EOL;
}

echo PHP_EOL . "=== MEDIA ASSETS EN BASE DE DATOS ===" . PHP_EOL;
$assets = MediaAsset::all();
echo "Total MediaAssets registrados: " . $assets->count() . PHP_EOL;
$missing = 0;
foreach ($assets as $a) {
    $exists = $storage->exists($mediaBucket, $a->storage_path);
    if (!$exists) {
        $missing++;
        echo "  [FALTA] Path: {$a->storage_path}" . PHP_EOL;
    }
}
echo "Total verificados existentes: " . ($assets->count() - $missing) . " | Faltantes: {$missing}" . PHP_EOL;

