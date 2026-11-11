<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SourceDocument;
use App\Models\Question;

echo "=== VERIFICANDO DOCUMENTO MATEMATICAS 2026 ===" . PHP_EOL;
$docMath = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
echo "ID: {$docMath->id} | Titulo: {$docMath->title} | Status: {$docMath->status}" . PHP_EOL;
$mathQuestions = Question::where('source_document_id', $docMath->id)->get();
echo "Total preguntas Matematicas: " . $mathQuestions->count() . PHP_EOL;
$verifiedMath = $mathQuestions->where('key_status', 'verified')->count();
echo "Claves verificadas: {$verifiedMath} / " . $mathQuestions->count() . PHP_EOL;
$withWarnings = $mathQuestions->filter(fn($q) => !empty($q->extraction_warnings))->count();
echo "Preguntas con observaciones: {$withWarnings}" . PHP_EOL;
echo "Preguntas en borrador (draft): " . $mathQuestions->where('status', 'draft')->count() . PHP_EOL;

// Check questions 12, 24, 42, 43, 50
foreach ([12, 24, 42, 43, 50] as $num) {
    $q = $mathQuestions->where('order_in_group', $num)->first();
    $hasMedia = $q ? $q->confirmedMediaAssets()->count() : 0;
    $optsWithMedia = $q ? $q->options()->whereNotNull('option_image_path')->count() : 0;
    echo "  Pregunta #{$num}: clave=" . ($q ? $q->correct_option : 'N/A') . " | warnings=" . ($q ? ($q->extraction_warnings ?: 'ninguno') : 'N/A') . " | question_media={$hasMedia} | options_with_media={$optsWithMedia}" . PHP_EOL;
}

echo PHP_EOL . "=== VERIFICANDO AUDITORIA REGISTRADA EN METADATA ===" . PHP_EOL;
$meta = $docMath->extraction_metadata ?? [];
$audit = $meta['quality_audit'] ?? null;
if ($audit) {
    echo "Auditoria status: " . ($audit['status'] ?? 'N/A') . PHP_EOL;
    echo "Area detectada: " . ($audit['detected_subject'] ?? 'N/A') . PHP_EOL;
    echo "Total preguntas: " . ($audit['total_questions'] ?? 0) . PHP_EOL;
    echo "Listas para aprobacion: " . ($audit['ready_for_approval_count'] ?? 0) . PHP_EOL;
    echo "Requieren revision: " . ($audit['requires_review_count'] ?? 0) . PHP_EOL;
    echo "Claves oficiales encontradas: " . ($audit['official_keys_found'] ?? 0) . PHP_EOL;
    echo "Claves verificadas: " . ($audit['verified_keys_count'] ?? 0) . PHP_EOL;
} else {
    echo "No hay auditoria en metadata." . PHP_EOL;
}

echo PHP_EOL . "=== VERIFICANDO DOCUMENTO LECTURA CRITICA (INTACTO) ===" . PHP_EOL;
$docLC = SourceDocument::find('f59ac90b-509a-493d-a75b-ae85c4c0cadf');
echo "ID: {$docLC->id} | Titulo: {$docLC->title} | Status: {$docLC->status}" . PHP_EOL;
$lcQuestions = Question::where('source_document_id', $docLC->id)->get();
echo "Total preguntas Lectura Critica: " . $lcQuestions->count() . PHP_EOL;
echo "Claves verificadas: " . $lcQuestions->where('key_status', 'verified')->count() . PHP_EOL;
echo "Publicadas: " . $lcQuestions->where('status', 'published')->count() . PHP_EOL;

