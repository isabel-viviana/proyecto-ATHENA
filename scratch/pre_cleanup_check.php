<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\SupabaseStorageService;

$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$lcDocId = 'f59ac90b-509a-493d-a75b-ae85c4c0cadf';

echo "=== VERIFICACION PRE-LIMPIEZA DE CONTEOS Y ESTADOS ===" . PHP_EOL . PHP_EOL;

// 1. Matemáticas 2026 (Documento de prueba a sanear)
$mathDoc = DB::connection('question_bank')->table('source_documents')->where('id', $mathDocId)->first();
if (!$mathDoc) {
    die("ERROR: El documento de Matemáticas {$mathDocId} no fue encontrado." . PHP_EOL);
}

$mathQuestions = DB::connection('question_bank')->table('questions')->where('source_document_id', $mathDocId)->get();
$mathQIds = $mathQuestions->pluck('id')->toArray();
$mathOptions = DB::connection('question_bank')->table('question_options')->whereIn('question_id', $mathQIds)->get();
$mathStimuli = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $mathDocId)->get();
$mathMedia = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $mathDocId)->get();

$mathStudentAnswers = DB::connection('question_bank')->table('student_answers')->whereIn('question_id', $mathQIds)->count();
$mathExamAnswers = DB::connection('question_bank')->table('exam_attempt_answers')->whereIn('question_id', $mathQIds)->count();

echo "DOCUMENTO DE PRUEBA A SANEAR (Matemáticas 2026):" . PHP_EOL;
echo "  - Titulo: {$mathDoc->title}" . PHP_EOL;
echo "  - Storage path: {$mathDoc->storage_path}" . PHP_EOL;
echo "  - Status: {$mathDoc->status}" . PHP_EOL;
echo "  - Preguntas: " . $mathQuestions->count() . PHP_EOL;
echo "    * En draft: " . $mathQuestions->where('status', 'draft')->count() . PHP_EOL;
echo "    * En review: " . $mathQuestions->where('status', 'review')->count() . PHP_EOL;
echo "    * En published: " . $mathQuestions->where('status', 'published')->count() . PHP_EOL;
echo "  - Opciones de respuesta: " . $mathOptions->count() . PHP_EOL;
echo "  - Estímulos: " . $mathStimuli->count() . PHP_EOL;
echo "  - Media Assets: " . $mathMedia->count() . PHP_EOL;
echo "  - [CRITICO] student_answers asociados: {$mathStudentAnswers}" . PHP_EOL;
echo "  - [CRITICO] exam_attempt_answers asociados: {$mathExamAnswers}" . PHP_EOL;

// 2. Lectura Crítica (Documento Oficial Intocable)
$lcDoc = DB::connection('question_bank')->table('source_documents')->where('id', $lcDocId)->first();
if (!$lcDoc) {
    die("ERROR CRITICO: El documento de Lectura Crítica {$lcDocId} no fue encontrado." . PHP_EOL);
}

$lcQuestions = DB::connection('question_bank')->table('questions')->where('source_document_id', $lcDocId)->get();
$lcQIds = $lcQuestions->pluck('id')->toArray();
$lcOptions = DB::connection('question_bank')->table('question_options')->whereIn('question_id', $lcQIds)->get();
$lcStimuli = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $lcDocId)->get();
$lcMedia = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $lcDocId)->get();

$lcStudentAnswers = DB::connection('question_bank')->table('student_answers')->whereIn('question_id', $lcQIds)->count();
$lcExamAnswers = DB::connection('question_bank')->table('exam_attempt_answers')->whereIn('question_id', $lcQIds)->count();

echo PHP_EOL . "DOCUMENTO INTOCABLE (Lectura Crítica):" . PHP_EOL;
echo "  - Titulo: {$lcDoc->title}" . PHP_EOL;
echo "  - Storage path: {$lcDoc->storage_path}" . PHP_EOL;
echo "  - Status: {$lcDoc->status}" . PHP_EOL;
echo "  - Preguntas: " . $lcQuestions->count() . " (Todas published: " . $lcQuestions->where('status', 'published')->count() . ")" . PHP_EOL;
echo "  - Opciones: " . $lcOptions->count() . PHP_EOL;
echo "  - Estímulos: " . $lcStimuli->count() . PHP_EOL;
echo "  - Media Assets: " . $lcMedia->count() . PHP_EOL;
echo "  - student_answers asociados: {$lcStudentAnswers}" . PHP_EOL;
echo "  - exam_attempt_answers asociados: {$lcExamAnswers}" . PHP_EOL;

// 3. Storage check
$storage = app(SupabaseStorageService::class);
$docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

echo PHP_EOL . "STORAGE FILES A ELIMINAR (Matemáticas 2026):" . PHP_EOL;
$mathDocExists = $storage->exists($docBucket, $mathDoc->storage_path);
echo "  - PDF {$mathDoc->storage_path}: " . ($mathDocExists ? 'EXISTE' : 'NO EXISTE') . PHP_EOL;

$storageMediaFiles = [];
foreach ($mathMedia as $m) {
    $exists = $storage->exists($mediaBucket, $m->storage_path);
    $storageMediaFiles[] = ['path' => $m->storage_path, 'exists' => $exists];
}
echo "  - Media Assets en Storage: " . count($storageMediaFiles) . " registros." . PHP_EOL;

$optImages = $mathOptions->whereNotNull('option_image_path')->pluck('option_image_path')->unique();
echo "  - Imágenes de opciones en Storage: " . $optImages->count() . " archivos." . PHP_EOL;

// Validar que no haya impedimento
if ($mathStudentAnswers > 0 || $mathExamAnswers > 0) {
    die("ALERTA: El documento de prueba tiene respuestas de estudiantes vinculadas. DETENIENDO." . PHP_EOL);
}

if ($mathQuestions->count() !== 50 || $mathOptions->count() !== 200) {
    die("ALERTA: Las cantidades de preguntas/opciones de prueba difieren de lo esperado (50/200). DETENIENDO." . PHP_EOL);
}

echo PHP_EOL . ">>> VALIDACION EXITOSA: Los conteos y estados coinciden exactamente con lo auditado. Listo para saneamiento. <<<" . PHP_EOL;

