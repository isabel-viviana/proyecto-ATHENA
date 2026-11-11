<?php
require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('GEMINI_API_KEY');

function checkQuick($m, $key) {
    $t = microtime(true);
    $res = Http::timeout(20)->post("https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$key}", [
        'contents' => [['parts' => [['text' => 'Hi']]]]
    ]);
    $el = round(microtime(true) - $t, 2);
    echo "$m: status " . $res->status() . " in {$el}s\n";
    if (!$res->successful()) {
        $err = $res->json()['error']['message'] ?? $res->body();
        echo "   Error: " . substr($err, 0, 100) . "\n";
    }
}

checkQuick('gemini-3.5-flash-lite', $apiKey);
checkQuick('gemini-3.5-flash', $apiKey);
checkQuick('gemini-3-flash-preview', $apiKey);
checkQuick('gemini-3.1-flash-lite', $apiKey);

