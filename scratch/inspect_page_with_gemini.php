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

echo "Consultando Gemini para transcripción exacta de la página 18...\n";

$res = Http::timeout(60)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
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
                    'text' => 'Por favor transcribe de forma literal e idéntica todo el texto visible en la página 18 (página física número 18 del cuadernillo, correspondiente a la Pregunta 24), incluyendo cualquier enunciado introductorio, figuras, descripción de figuras P, Q, R, S, dimensiones y todas las opciones.',
                ],
            ],
        ],
    ],
]);

if ($res->successful()) {
    echo $res->json()['candidates'][0]['content']['parts'][0]['text'] . "\n";
} else {
    echo "Error: " . $res->body() . "\n";
}

