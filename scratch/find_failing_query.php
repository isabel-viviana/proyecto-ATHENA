<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('question_bank');
$conn->reconnect();

$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';

echo "Probando paso a paso..." . PHP_EOL;

$qIds = $conn->table('questions')->where('source_document_id', $mathDocId)->pluck('id')->toArray();
echo "Q count: " . count($qIds) . PHP_EOL;

// 1. Options
try {
    $d1 = $conn->table('question_options')->whereIn('question_id', $qIds)->delete();
    echo "Options: {$d1}" . PHP_EOL;
} catch (\Throwable $e) {
    echo "Options ERROR: " . $e->getMessage() . PHP_EOL;
}

// 2. Media
try {
    $d2 = $conn->table('media_assets')->where('source_document_id', $mathDocId)->delete();
    echo "Media: {$d2}" . PHP_EOL;
} catch (\Throwable $e) {
    echo "Media ERROR: " . $e->getMessage() . PHP_EOL;
}

// 3. Questions
try {
    $d3 = $conn->table('questions')->where('source_document_id', $mathDocId)->delete();
    echo "Questions: {$d3}" . PHP_EOL;
} catch (\Throwable $e) {
    echo "Questions ERROR: " . $e->getMessage() . PHP_EOL;
}

// 4. Stimuli
try {
    $d4 = $conn->table('stimuli')->where('source_document_id', $mathDocId)->delete();
    echo "Stimuli: {$d4}" . PHP_EOL;
} catch (\Throwable $e) {
    echo "Stimuli ERROR: " . $e->getMessage() . PHP_EOL;
}

// 5. Source doc
try {
    $d5 = $conn->table('source_documents')->where('id', $mathDocId)->delete();
    echo "SourceDoc: {$d5}" . PHP_EOL;
} catch (\Throwable $e) {
    echo "SourceDoc ERROR: " . $e->getMessage() . PHP_EOL;
}

