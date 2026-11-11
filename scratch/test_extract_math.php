<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\Http;

$doc = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
echo "Document: " . $doc->title . "\n";

$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$pdfBytes = $storage->get($bucket, $doc->storage_path);
echo "PDF size: " . strlen($pdfBytes) . " bytes\n";

$base64Pdf = base64_encode($pdfBytes);
$apiKey = env('GEMINI_API_KEY');
$model = 'gemini-3.5-flash-lite';

echo "Testing extraction call to $model...\n";
$start = microtime(true);

$prompt = <<<PROMPT
Eres un experto pedagógico y extractor multimodal de pruebas oficiales ICFES Saber 11 de Colombia.
Analiza el documento PDF adjunto con máxima fidelidad tanto visual como textual a lo largo de todas sus páginas.
Reconstruye unidades completas de preguntas con sus textos de lectura, estímulos, viñetas, tablas, gráficas, opciones y fórmulas matemáticas.

INSTRUCCIÓN DE COBERTURA:
- Extrae todas las preguntas de este cuadernillo de Matemáticas en orden correlativo (order: 1, 2, ...).
- Cada pregunta debe contener: order, source_page, subject_name ("Matemáticas"), statement, difficulty ("easy"|"medium"|"hard"), has_verifiable_answer, correct_option_letter, opciones A, B, C, D con su texto.
- Si una pregunta depende de un estímulo compartido (tabla, gráfica, texto introductorio), regístralo en stimuli con su id_tag.

Responde ÚNICAMENTE con un objeto JSON válido con la clave "questions" (arreglo de preguntas) y "stimuli" (arreglo de estímulos).
PROMPT;

$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$res = Http::timeout(300)->post($endpoint, [
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
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'temperature'      => 0.1,
        'maxOutputTokens'  => 65536,
    ],
]);

$elapsed = round(microtime(true) - $start, 2);
echo "Status: " . $res->status() . " (in {$elapsed}s)\n";

if ($res->successful()) {
    $rawText = $res->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $json = json_decode($rawText, true);
    $qCount = count($json['questions'] ?? []);
    $sCount = count($json['stimuli'] ?? []);
    echo "Extracted questions count: " . $qCount . "\n";
    echo "Extracted stimuli count: " . $sCount . "\n";
    if ($qCount > 0) {
        $firstQ = $json['questions'][0];
        echo "First question:\n";
        echo " - Order: " . ($firstQ['order'] ?? '') . "\n";
        echo " - Page: " . ($firstQ['source_page'] ?? '') . "\n";
        echo " - Statement: " . ($firstQ['statement'] ?? '') . "\n";
        echo " - Options count: " . count($firstQ['options'] ?? []) . "\n";
    }
} else {
    echo "Error:\n" . $res->body() . "\n";
}

