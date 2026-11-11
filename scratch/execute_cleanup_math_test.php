<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\SupabaseStorageService;

$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$lcDocId = 'f59ac90b-509a-493d-a75b-ae85c4c0cadf';

echo "=== EJECUTANDO SANEAMIENTO SELECTIVO (DOCUMENTO {$mathDocId}) ===" . PHP_EOL . PHP_EOL;

$storage = app(SupabaseStorageService::class);
$docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

// 1. Obtener lista de rutas de Storage a eliminar
$mathDoc = DB::connection('question_bank')->table('source_documents')->where('id', $mathDocId)->first();
if (!$mathDoc) {
    die("ERROR: Documento {$mathDocId} no encontrado." . PHP_EOL);
}

$pdfPath = $mathDoc->storage_path;
$mediaAssetPaths = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $mathDocId)->pluck('storage_path')->toArray();
$mathQIds = DB::connection('question_bank')->table('questions')->where('source_document_id', $mathDocId)->pluck('id')->toArray();
$optionImagePaths = DB::connection('question_bank')->table('question_options')->whereIn('question_id', $mathQIds)->whereNotNull('option_image_path')->pluck('option_image_path')->unique()->toArray();

echo "Archivos de Storage identificados para eliminación:" . PHP_EOL;
echo "  - PDF: {$pdfPath}" . PHP_EOL;
echo "  - Media assets: " . count($mediaAssetPaths) . " archivos" . PHP_EOL;
echo "  - Option images: " . count($optionImagePaths) . " archivos" . PHP_EOL . PHP_EOL;

// 2. Ejecutar eliminación relacional en PostgreSQL dentro de transacción
DB::connection('question_bank')->beginTransaction();

try {
    echo "Iniciando transacción PostgreSQL..." . PHP_EOL;

    // 2.1 Eliminar question_options
    $deletedOpts = DB::connection('question_bank')->table('question_options')->whereIn('question_id', $mathQIds)->delete();
    echo "  [1/5] question_options eliminadas: {$deletedOpts}" . PHP_EOL;

    // 2.2 Eliminar media_assets directamente (respetando check constraint media_assets_status_target_check)
    $deletedMedia = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $mathDocId)->delete();
    echo "  [2/5] media_assets eliminados: {$deletedMedia}" . PHP_EOL;

    // 2.3 Eliminar questions
    $deletedQsts = DB::connection('question_bank')->table('questions')->where('source_document_id', $mathDocId)->delete();
    echo "  [3/5] questions eliminadas: {$deletedQsts}" . PHP_EOL;

    // 2.4 Eliminar stimuli
    $deletedStim = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $mathDocId)->delete();
    echo "  [4/5] stimuli eliminados: {$deletedStim}" . PHP_EOL;

    // 2.5 Eliminar source_documents
    $deletedDocs = DB::connection('question_bank')->table('source_documents')->where('id', $mathDocId)->delete();
    echo "  [5/5] source_documents eliminado: {$deletedDocs}" . PHP_EOL;

    DB::connection('question_bank')->commit();
    echo ">>> TRANSACCION POSTGRESQL CONFIRMADA CON EXITO (COMMIT) <<<" . PHP_EOL . PHP_EOL;

} catch (\Throwable $e) {
    DB::connection('question_bank')->rollBack();
    echo ">>> ERROR EN TRANSACCION: ROLLBACK EJECUTADO. Causa: " . $e->getMessage() . PHP_EOL;
    die("Abortando." . PHP_EOL);
}

// 3. Eliminar archivos de Storage en Supabase
echo "=== ELIMINANDO OBJETOS EN SUPABASE STORAGE ===" . PHP_EOL;

// 3.1 Eliminar PDF
if (!empty($pdfPath) && $storage->exists($docBucket, $pdfPath)) {
    $del = $storage->delete($docBucket, $pdfPath);
    echo "  - PDF {$pdfPath}: " . ($del ? 'ELIMINADO' : 'ERROR') . PHP_EOL;
}

// 3.2 Eliminar Media Assets
$deletedMediaCount = 0;
foreach ($mediaAssetPaths as $p) {
    if ($storage->exists($mediaBucket, $p)) {
        if ($storage->delete($mediaBucket, $p)) {
            $deletedMediaCount++;
        }
    }
}
echo "  - Media Assets en Storage eliminados: {$deletedMediaCount} de " . count($mediaAssetPaths) . PHP_EOL;

// 3.3 Eliminar Option Images
$deletedOptCount = 0;
foreach ($optionImagePaths as $p) {
    if ($storage->exists($mediaBucket, $p)) {
        if ($storage->delete($mediaBucket, $p)) {
            $deletedOptCount++;
        }
    }
}
echo "  - Option Images en Storage eliminadas: {$deletedOptCount} de " . count($optionImagePaths) . PHP_EOL;

// 3.4 Eliminar manifest.json si existe
$manifestPath = "extracted/{$mathDocId}/manifest.json";
if ($storage->exists($mediaBucket, $manifestPath)) {
    $storage->delete($mediaBucket, $manifestPath);
    echo "  - Manifest.json eliminado: {$manifestPath}" . PHP_EOL;
}

// 4. Verificación Post-Limpieza
echo PHP_EOL . "=== VERIFICACION POST-LIMPIEZA EN BASE DE DATOS ===" . PHP_EOL;

$remainingMathDoc = DB::connection('question_bank')->table('source_documents')->where('id', $mathDocId)->count();
$remainingMathQ = DB::connection('question_bank')->table('questions')->where('source_document_id', $mathDocId)->count();
$remainingMathOpt = DB::connection('question_bank')->table('question_options')->whereIn('question_id', $mathQIds)->count();
$remainingMathStim = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $mathDocId)->count();
$remainingMathMed = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $mathDocId)->count();

echo "Documento {$mathDocId} en base de datos:" . PHP_EOL;
echo "  - source_documents restantes: {$remainingMathDoc} (esperado: 0)" . PHP_EOL;
echo "  - questions restantes: {$remainingMathQ} (esperado: 0)" . PHP_EOL;
echo "  - question_options restantes: {$remainingMathOpt} (esperado: 0)" . PHP_EOL;
echo "  - stimuli restantes: {$remainingMathStim} (esperado: 0)" . PHP_EOL;
echo "  - media_assets restantes: {$remainingMathMed} (esperado: 0)" . PHP_EOL;

// 5. Verificación de Conservación de Lectura Crítica
echo PHP_EOL . "=== VERIFICACION DE CONSERVACION DE LECTURA CRITICA (INTACTO) ===" . PHP_EOL;
$lcDoc = DB::connection('question_bank')->table('source_documents')->where('id', $lcDocId)->first();
$lcQCount = DB::connection('question_bank')->table('questions')->where('source_document_id', $lcDocId)->count();
$lcPubCount = DB::connection('question_bank')->table('questions')->where('source_document_id', $lcDocId)->where('status', 'published')->count();
$lcOptCount = DB::connection('question_bank')->table('question_options')->join('questions', 'question_options.question_id', '=', 'questions.id')->where('questions.source_document_id', $lcDocId)->count();
$lcStimCount = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $lcDocId)->count();
$lcMedCount = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $lcDocId)->count();
$lcAnsCount = DB::connection('question_bank')->table('student_answers')->count();
$lcExamAnsCount = DB::connection('question_bank')->table('exam_attempt_answers')->count();
$lcExamAttCount = DB::connection('question_bank')->table('exam_attempts')->count();

echo "Documento Lectura Crítica ({$lcDocId}):" . PHP_EOL;
echo "  - Documento existe: " . ($lcDoc ? 'SI' : 'NO') . " ('{$lcDoc->title}')" . PHP_EOL;
echo "  - Preguntas: {$lcQCount} (esperado: 49) | Publicadas: {$lcPubCount} (esperado: 49)" . PHP_EOL;
echo "  - Opciones: {$lcOptCount} (esperado: 196)" . PHP_EOL;
echo "  - Estímulos: {$lcStimCount} (esperado: 19)" . PHP_EOL;
echo "  - Media Assets: {$lcMedCount} (esperado: 9)" . PHP_EOL;
echo "  - student_answers en BD: {$lcAnsCount} (esperado: 10)" . PHP_EOL;
echo "  - exam_attempt_answers en BD: {$lcExamAnsCount} (esperado: 10)" . PHP_EOL;
echo "  - exam_attempts en BD: {$lcExamAttCount} (esperado: 1)" . PHP_EOL;

if ($remainingMathDoc === 0 && $remainingMathQ === 0 && $lcQCount === 49 && $lcPubCount === 49 && $lcAnsCount === 10) {
    echo PHP_EOL . ">>> RESULTADO: SANEAMIENTO COMPLETADO EXITOSAMENTE Y LECTURA CRITICA 100% INTACTA <<<" . PHP_EOL;
} else {
    echo PHP_EOL . ">>> ALERTA EN VERIFICACION POST-LIMPIEZA <<<" . PHP_EOL;
}
