<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\MediaAsset;
use App\Services\SupabaseStorageService;

$docId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$doc = SourceDocument::find($docId);

if (!$doc) {
    die("Document not found\n");
}

echo "=== AUDITORÍA DOCUMENTO MATEMÁTICAS 2026 ===\n";
echo "ID: {$doc->id}\n";
echo "Título: {$doc->title}\n";
echo "Estado: {$doc->status}\n";
echo "Metadata: " . json_encode($doc->extraction_metadata) . "\n\n";

// 1. Cargar preguntas
$questions = Question::where('source_document_id', $docId)
    ->with(['options' => function($q) {
        $q->orderBy('option_letter', 'asc');
    }, 'stimulus'])
    ->orderBy('order_in_group', 'asc')
    ->get();

echo "Total preguntas cargadas: " . $questions->count() . "\n";

// Guardar volcado estructurado en JSON para análisis exhaustivo
$dump = [];
foreach ($questions as $q) {
    $opts = [];
    foreach ($q->options as $o) {
        $opts[$o->option_letter] = [
            'text' => $o->option_text,
            'is_correct' => $o->is_correct,
        ];
    }
    $dump[] = [
        'id' => $q->id,
        'order' => $q->order_in_group,
        'page' => $q->source_page,
        'statement' => $q->statement,
        'has_visual_media' => $q->has_visual_media,
        'visual_type' => $q->visual_type,
        'visual_transcription' => $q->visual_transcription,
        'visual_description' => $q->visual_description,
        'stimulus_id' => $q->stimulus_id,
        'stimulus_title' => $q->stimulus?->title,
        'key_status' => $q->key_status,
        'difficulty' => $q->difficulty,
        'options' => $opts,
    ];
}

file_put_contents(__DIR__ . '/math_questions_dump.json', json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "Guardado volcado de las 50 preguntas en scratch/math_questions_dump.json\n\n";

// Estadísticas rápidas
$withVisual = $questions->filter(fn($q) => $q->has_visual_media)->count();
$withStimulus = $questions->filter(fn($q) => !empty($q->stimulus_id))->count();
$verifiedKeys = $questions->filter(fn($q) => $q->key_status === 'verified')->count();
$unverifiedKeys = $questions->filter(fn($q) => $q->key_status !== 'verified')->count();

echo "Estadísticas preliminares:\n";
echo " - Con visual media: {$withVisual}\n";
echo " - Con estímulo compartido: {$withStimulus}\n";
echo " - Claves marcadas verified: {$verifiedKeys}\n";
echo " - Claves sin verificar / unverified: {$unverifiedKeys}\n";

// Verificación de orden 1..50
$orders = $questions->pluck('order_in_group')->toArray();
$expectedOrders = range(1, 50);
$diff = array_diff($expectedOrders, $orders);
if (empty($diff)) {
    echo " - Orden de preguntas 1 a 50: COMPLETO Y CORRELATIVO\n";
} else {
    echo " - Faltan órdenes: " . implode(', ', $diff) . "\n";
}

// Verificación de 4 opciones por pregunta
$incompleteOptions = [];
foreach ($questions as $q) {
    if ($q->options->count() !== 4) {
        $incompleteOptions[] = $q->order_in_group;
    }
}
if (empty($incompleteOptions)) {
    echo " - Todas las 50 preguntas tienen exactamente 4 opciones A, B, C, D: SÍ\n";
} else {
    echo " - Preguntas con opciones incompletas: " . implode(', ', $incompleteOptions) . "\n";
}

