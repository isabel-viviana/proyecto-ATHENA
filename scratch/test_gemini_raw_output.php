<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use App\Services\GeminiQuestionExtractionService;
use Illuminate\Support\Facades\Http;

$doc = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$pdfBytes = $storage->get($bucket, $doc->storage_path);
$base64Pdf = base64_encode($pdfBytes);

$service = app(GeminiQuestionExtractionService::class);
$ref = new ReflectionClass($service);
$promptMethod = $ref->getMethod('buildPromptInstruction');
$promptMethod->setAccessible(true);
$prompt = $promptMethod->invoke($service, $doc, 35);

$apiKey = env('GEMINI_API_KEY');
$model = 'gemini-3-flash-preview';

echo "Sending extraction request to $model...\n";
$start = microtime(true);
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
    $cand = $res->json()['candidates'][0] ?? [];
    echo "Finish reason: " . ($cand['finishReason'] ?? 'none') . "\n";
    $raw = $cand['content']['parts'][0]['text'] ?? '';
    echo "Raw text length: " . strlen($raw) . " chars\n";
    echo "First 200 chars:\n" . substr($raw, 0, 200) . "\n\n";
    echo "Last 200 chars:\n" . substr($raw, -200) . "\n\n";

    file_put_contents(__DIR__ . '/gemini_raw_response.json', $raw);
    echo "Saved to scratch/gemini_raw_response.json\n";

    $decoded = json_decode($raw, true);
    if ($decoded === null) {
        echo "json_decode failed! Error: " . json_last_error_msg() . "\n";
    } else {
        echo "json_decode succeeded! Questions count: " . count($decoded['questions'] ?? []) . "\n";
    }
} else {
    echo "Error:\n" . $res->body() . "\n";
}

