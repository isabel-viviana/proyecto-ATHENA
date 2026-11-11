<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('GEMINI_API_KEY');

function testModel($model, $apiKey) {
    echo "\nTesting model: $model...\n";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $res = Http::timeout(25)->post($url, [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Di exactamente la palabra "HOLA".']
                ]
            ]
        ]
    ]);
    echo "Status: " . $res->status() . "\n";
    if ($res->successful()) {
        $text = $res->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
        echo "Response: " . trim($text) . "\n";
    } else {
        $err = $res->json()['error']['message'] ?? $res->body();
        echo "Error: " . substr($err, 0, 150) . "...\n";
    }
}

testModel('gemini-3.8-flash', $apiKey);
testModel('gemini-3.5-flash', $apiKey);
testModel('gemini-3.1-flash-lite-preview', $apiKey);
testModel('gemini-2.5-flash-lite', $apiKey);
testModel('gemini-3.1-pro-preview', $apiKey);
