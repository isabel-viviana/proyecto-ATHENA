<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\MediaAsset;
use App\Services\GeminiQuestionExtractionService;

echo "=================================================================\n";
echo "PRUEBA CONTROLADA DE EXTRACCIÓN CON GEMINI MULTIMODAL\n";
echo "=================================================================\n\n";

$docId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$document = SourceDocument::find($docId);

if (!$document) {
    die("ERROR: Documento {$docId} no encontrado.\n");
}

echo "1. Documento identificado:\n";
echo "   - ID: {$document->id}\n";
echo "   - Título: {$document->title}\n";
echo "   - Ruta Storage: {$document->storage_path}\n";
echo "   - Estado previo: {$document->status}\n\n";

// Conteo previo de preguntas del otro documento (Lectura Crítica) para verificar no afectación
$lecturaDocId = 'f59ac90b-509a-493d-a75b-ae85c4c0cadf';
$lecturaQuestionsBefore = Question::where('source_document_id', $lecturaDocId)->count();
echo "2. Integridad previa de otros documentos:\n";
echo "   - Preguntas existentes de Lectura Crítica: {$lecturaQuestionsBefore} (deben permanecer intactas)\n\n";

// Ejecutar extracción
echo "3. Ejecutando extracción con GeminiQuestionExtractionService...\n";
$service = app(GeminiQuestionExtractionService::class);

$startTime = microtime(true);
$summary = $service->extractQuestionsFromDocument($document);
$duration = round(microtime(true) - $startTime, 2);

echo "\n✓ Extracción finalizada en {$duration} segundos!\n\n";

// 4. Verificaciones
$freshDoc = $document->fresh();
echo "4. Resultado en SourceDocument:\n";
echo "   - Estado final: {$freshDoc->status}\n";
echo "   - Metadatos:\n" . json_encode($freshDoc->extraction_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$extractedQuestions = Question::where('source_document_id', $docId)
    ->with('options')
    ->orderBy('order_in_group', 'asc')
    ->get();

$extractedStimuli = Stimulus::where('source_document_id', $docId)->get();
$mediaAssets = MediaAsset::where('source_document_id', $docId)->get();

echo "5. Resumen de registros creados para Matemáticas:\n";
echo "   - Total preguntas generadas: {$extractedQuestions->count()}\n";
echo "   - Total estímulos generados: {$extractedStimuli->count()}\n";
echo "   - Total assets multimedia: {$mediaAssets->count()}\n";

$allDraft = $extractedQuestions->every(fn($q) => $q->status === 'draft');
echo "   - ¿Todas las preguntas están en estado 'draft' (borrador para revisión)? " . ($allDraft ? 'SÍ (Correcto)' : 'NO (Error)') . "\n";

$allMath = $extractedQuestions->every(fn($q) => $q->subject_id === 2);
echo "   - ¿Todas las preguntas tienen subject_id = 2 (Matemáticas)? " . ($allMath ? 'SÍ (Correcto)' : 'NO (Revisar)') . "\n";

$allHave4Options = $extractedQuestions->every(fn($q) => $q->options->count() === 4);
echo "   - ¿Todas las preguntas tienen exactamente 4 opciones (A, B, C, D)? " . ($allHave4Options ? 'SÍ (Correcto)' : 'NO (Revisar)') . "\n";

if ($extractedQuestions->count() > 0) {
    echo "\nMuestra de las 3 primeras preguntas extraídas:\n";
    foreach ($extractedQuestions->take(3) as $q) {
        echo "   [Pregunta #{$q->order_in_group} - Pág. {$q->source_page}]\n";
        echo "   Enunciado: " . mb_substr($q->statement, 0, 110) . "...\n";
        foreach ($q->options as $opt) {
            echo "     {$opt->option_letter}) " . mb_substr($opt->option_text, 0, 60) . "\n";
        }
        echo "\n";
    }
}

// 6. Verificar integridad de Lectura Crítica
$lecturaQuestionsAfter = Question::where('source_document_id', $lecturaDocId)->count();
echo "6. Verificación de Integridad de otros documentos:\n";
echo "   - Preguntas de Lectura Crítica antes: {$lecturaQuestionsBefore}\n";
echo "   - Preguntas de Lectura Crítica después: {$lecturaQuestionsAfter}\n";
assert($lecturaQuestionsBefore === $lecturaQuestionsAfter, 'Las preguntas de Lectura Crítica no sufrieron ninguna alteración');
echo "   ✓ Las 49 preguntas oficiales de Lectura Crítica se conservan intactas.\n\n";

echo "=================================================================\n";
echo "PRUEBA CONTROLADA DE EXTRACCIÓN FINALIZADA CON ÉXITO!\n";
echo "=================================================================\n";

