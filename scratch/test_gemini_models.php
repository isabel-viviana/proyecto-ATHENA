<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('GEMINI_API_KEY');
echo "API Key length: " . strlen($apiKey) . " (starts with " . substr($apiKey, 0, 8) . "...)\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
$response = Http::get($url);

echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $models = $response->json()['models'] ?? [];
    echo "Total models available: " . count($models) . "\n";
    foreach ($models as $m) {
        $name = $m['name'] ?? '';
        $supported = $m['supportedGenerationMethods'] ?? [];
        if (in_array('generateContent', $supported)) {
            echo " - " . str_replace('models/', '', $name) . " (" . ($m['displayName'] ?? '') . ")\n";
        }
    }
} else {
    echo "Error body:\n" . $response->body() . "\n";
}

