<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stimulus;

$docId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$stimuli = Stimulus::where('source_document_id', $docId)->get();

echo "=== ESTÍMULOS ASOCIADOS EN BASE DE DATOS ===\n";
echo "Total estímulos: " . $stimuli->count() . "\n\n";

foreach ($stimuli as $idx => $s) {
    echo "ESTÍMULO #" . ($idx + 1) . ":\n";
    echo " - ID: {$s->id}\n";
    echo " - Título: {$s->title}\n";
    echo " - Página: {$s->source_page}\n";
    echo " - Instrucciones: {$s->instructions}\n";
    echo " - Tipo medio: {$s->media_type}\n";
    echo " - Contenido texto:\n" . $s->content_text . "\n";
    echo " - Transcripción visual: " . ($s->visual_transcription ?: '(ninguna)') . "\n";
    echo " - Descripción visual: " . ($s->visual_description ?: '(ninguna)') . "\n";
    echo "-----------------------------------------------------------------\n";
}

