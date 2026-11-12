<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\Http;

$doc = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$pdfBytes = $storage->get($bucket, $doc->storage_path);
$base64Pdf = base64_encode($pdfBytes);

$apiKey = env('GEMINI_API_KEY');
$model = 'gemini-3.5-flash';

echo "Consultando Gemini para inventario de contextos y estímulos compartidos...\n";

$prompt = <<<PROMPT
Analiza el cuadernillo completo de Matemáticas adjunto.
Enumera TODOS los contextos compartidos, textos introductorios o estímulos que agrupen dos o más preguntas (por ejemplo: "RESPONDA LAS PREGUNTAS X A Y DE ACUERDO CON...").
Para cada contexto compartido indica:
1. Título o tema.
2. Página física del PDF.
3. Rango exacto de preguntas que dependen de él (ej: preguntas 4 a 6).
4. Si incluye tabla, gráfica o figura.

Si una pregunta es independiente y no comparte contexto con otra, no la listes.
PROMPT;

$res = Http::timeout(90)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
    'contents' => [
        [
            'parts' => [
                [
                    'inlineData' => [
                        'mimeType' => 'application/pdf',
                        'data'     => $base64Pdf,
                    ],
                ],
                [
                    'text' => $prompt,
                ],
            ],
        ],
    ],
]);

if ($res->successful()) {
    $text = $res->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo $text . "\n";
    file_put_contents(__DIR__ . '/pdf_all_shared_stimuli.txt', $text);
} else {
    echo "Error: " . $res->body() . "\n";
}

