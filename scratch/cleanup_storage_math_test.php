<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SupabaseStorageService;

$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$storage = app(SupabaseStorageService::class);
$docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

echo "=== ELIMINANDO ARCHIVOS DE STORAGE PARA {$mathDocId} ===" . PHP_EOL;

// 1. PDF en icfes-documents
$pdfPath = "documents/2026/{$mathDocId}_09-marzo-cuadernillo-de-preguntas-matematicas-saber-11-2026.pdf";
if ($storage->exists($docBucket, $pdfPath)) {
    $res = $storage->delete($docBucket, $pdfPath, true);
    echo "PDF eliminado de {$docBucket}: " . ($res ? 'SI' : 'NO') . PHP_EOL;
} else {
    echo "PDF ya no existe en {$docBucket}." . PHP_EOL;
}

// 2. Archivos en icfes-media
$mediaFiles = [
    // Options
    "media/2026/b352295c/q12_chart_A.png",
    "media/2026/b352295c/q12_chart_B.png",
    "media/2026/b352295c/q12_chart_C.png",
    "media/2026/b352295c/q12_chart_D.png",
    "media/2026/b352295c/q42_design_A.png",
    "media/2026/b352295c/q42_design_B.png",
    "media/2026/b352295c/q42_design_C.png",
    "media/2026/b352295c/q42_design_D.png",
    "media/2026/b352295c/q43_func_A.png",
    "media/2026/b352295c/q43_func_B.png",
    "media/2026/b352295c/q43_func_C.png",
    "media/2026/b352295c/q43_func_D.png",
    // Questions / Stimuli figures
    "media/2026/b352295c/q24_square_points.png",
    "media/2026/b352295c/q42_soccer_field.png",
    "media/2026/b352295c/q50_packages.png",
    // Manifest
    "extracted/{$mathDocId}/manifest.json",
];

$deletedCount = 0;
foreach ($mediaFiles as $path) {
    if ($storage->exists($mediaBucket, $path)) {
        if ($storage->delete($mediaBucket, $path, true)) {
            $deletedCount++;
            echo "  [ELIMINADO] {$path}" . PHP_EOL;
        }
    } else {
        echo "  [NO EXISTE] {$path}" . PHP_EOL;
    }
}

// 3. Eliminar manifest.json si existe
$manifestPath = "extracted/{$mathDocId}/manifest.json";
if ($storage->exists($mediaBucket, $manifestPath)) {
    $storage->delete($mediaBucket, $manifestPath, true);
    echo "  - Manifest.json eliminado: {$manifestPath}" . PHP_EOL;
}

echo "Total archivos de media eliminados de Storage: {$deletedCount}" . PHP_EOL;
