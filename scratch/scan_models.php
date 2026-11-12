<?php
require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('GEMINI_API_KEY');

$models = [
    'gemini-2.5-flash',
    'gemini-2.5-flash-lite',
    'gemini-3.5-flash-lite',
    'gemini-3.5-flash',
];

foreach ($models as $m) {
    $t = microtime(true);
    $res = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$apiKey}", [
        'contents' => [['parts' => [['text' => 'Hi']]]]
    ]);
    $el = round(microtime(true) - $t, 2);
    echo "$m: status " . $res->status() . " in {$el}s\n";
    if (!$res->successful()) {
        $err = $res->json()['error']['message'] ?? $res->body();
        echo "   Error: " . substr($err, 0, 100) . "\n";
    }
}

