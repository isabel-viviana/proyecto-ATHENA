<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$lcDocId = 'f59ac90b-509a-493d-a75b-ae85c4c0cadf';

echo "=== VERIFICACION POST-SANEAMIENTO EN SUPABASE POSTGRESQL ===" . PHP_EOL . PHP_EOL;

// 1. Matemáticas 2026 (Debe ser 0)
$mathDoc = DB::connection('question_bank')->table('source_documents')->where('id', $mathDocId)->count();
$mathQ = DB::connection('question_bank')->table('questions')->where('source_document_id', $mathDocId)->count();
$mathStim = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $mathDocId)->count();
$mathMed = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $mathDocId)->count();

echo "Documento de prueba ({$mathDocId}):" . PHP_EOL;
echo "  - source_documents: {$mathDoc} (esperado: 0)" . PHP_EOL;
echo "  - questions: {$mathQ} (esperado: 0)" . PHP_EOL;
echo "  - stimuli: {$mathStim} (esperado: 0)" . PHP_EOL;
echo "  - media_assets: {$mathMed} (esperado: 0)" . PHP_EOL;

// 2. Lectura Crítica (Debe conservar 49 preguntas, 196 opciones, etc.)
$lcDoc = DB::connection('question_bank')->table('source_documents')->where('id', $lcDocId)->first();
$lcQ = DB::connection('question_bank')->table('questions')->where('source_document_id', $lcDocId)->get();
$lcQPub = $lcQ->where('status', 'published')->count();
$lcStim = DB::connection('question_bank')->table('stimuli')->where('source_document_id', $lcDocId)->count();
$lcMed = DB::connection('question_bank')->table('media_assets')->where('source_document_id', $lcDocId)->count();
$lcOpt = DB::connection('question_bank')->table('question_options')->join('questions', 'question_options.question_id', '=', 'questions.id')->where('questions.source_document_id', $lcDocId)->count();
$lcAns = DB::connection('question_bank')->table('student_answers')->count();
$lcExamAns = DB::connection('question_bank')->table('exam_attempt_answers')->count();
$lcExamAtt = DB::connection('question_bank')->table('exam_attempts')->count();

echo PHP_EOL . "Documento Oficial Lectura Crítica ({$lcDocId}):" . PHP_EOL;
echo "  - Titulo: " . ($lcDoc ? $lcDoc->title : 'NO EXISTE') . PHP_EOL;
echo "  - Preguntas totales: " . $lcQ->count() . " (esperado: 49)" . PHP_EOL;
echo "  - Preguntas publicadas: {$lcQPub} (esperado: 49)" . PHP_EOL;
echo "  - Opciones de respuesta: {$lcOpt} (esperado: 196)" . PHP_EOL;
echo "  - Estímulos: {$lcStim} (esperado: 19)" . PHP_EOL;
echo "  - Media assets: {$lcMed} (esperado: 9)" . PHP_EOL;
echo "  - student_answers: {$lcAns} (esperado: 10)" . PHP_EOL;
echo "  - exam_attempt_answers: {$lcExamAns} (esperado: 10)" . PHP_EOL;
echo "  - exam_attempts: {$lcExamAtt} (esperado: 1)" . PHP_EOL;

// 3. Totales globales en el banco
echo PHP_EOL . "TOTALES GLOBALES EN SUPABASE POSTGRESQL:" . PHP_EOL;
echo "  - source_documents: " . DB::connection('question_bank')->table('source_documents')->count() . PHP_EOL;
echo "  - questions: " . DB::connection('question_bank')->table('questions')->count() . PHP_EOL;
echo "  - question_options: " . DB::connection('question_bank')->table('question_options')->count() . PHP_EOL;
echo "  - stimuli: " . DB::connection('question_bank')->table('stimuli')->count() . PHP_EOL;
echo "  - media_assets: " . DB::connection('question_bank')->table('media_assets')->count() . PHP_EOL;
echo "  - subjects: " . DB::connection('question_bank')->table('subjects')->count() . PHP_EOL;

if ($mathDoc === 0 && $mathQ === 0 && $lcQ->count() === 49 && $lcQPub === 49 && $lcAns === 10) {
    echo PHP_EOL . ">>> VERIFICACION POST-SANEAMIENTO 100% EXITOSA <<<" . PHP_EOL;
} else {
    echo PHP_EOL . ">>> ERROR EN VERIFICACION POST-SANEAMIENTO <<<" . PHP_EOL;
}

