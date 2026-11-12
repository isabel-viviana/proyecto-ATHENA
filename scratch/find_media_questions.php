<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;
use App\Models\MediaAsset;

echo "=== PREGUNTAS CON IMÁGENES ASOCIADAS ===\n";
$questions = Question::where('status', 'published')
    ->with(['stimulus.confirmedMediaAssets', 'confirmedMediaAssets'])
    ->orderBy('order_in_group', 'asc')
    ->get();

foreach ($questions as $q) {
    $hasStimMedia = $q->stimulus && $q->stimulus->confirmedMediaAssets->isNotEmpty();
    $hasQMedia = $q->confirmedMediaAssets->isNotEmpty();
    if ($hasStimMedia || $hasQMedia) {
        $stimTitle = $q->stimulus ? $q->stimulus->title : 'N/A';
        $media = $hasStimMedia ? $q->stimulus->confirmedMediaAssets->first() : $q->confirmedMediaAssets->first();
        echo "Pregunta #{$q->order_in_group} (ID: {$q->id}): Estímulo: '{$stimTitle}', Media: {$media->storage_path}, Page: {$media->source_page}\n";
    }
}

