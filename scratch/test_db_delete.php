<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('question_bank');
$conn->reconnect();

$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';

echo "Probando transacción con reconexión..." . PHP_EOL;

try {
    $conn->beginTransaction();

    $qIds = $conn->table('questions')->where('source_document_id', $mathDocId)->pluck('id')->toArray();
    echo "Q count: " . count($qIds) . PHP_EOL;

    $d1 = $conn->table('question_options')->whereIn('question_id', $qIds)->delete();
    echo "Options deleted: {$d1}" . PHP_EOL;

    $d2 = $conn->table('media_assets')->where('source_document_id', $mathDocId)->delete();
    echo "Media deleted: {$d2}" . PHP_EOL;

    $d3 = $conn->table('questions')->where('source_document_id', $mathDocId)->delete();
    echo "Questions deleted: {$d3}" . PHP_EOL;

    $d4 = $conn->table('stimuli')->where('source_document_id', $mathDocId)->delete();
    echo "Stimuli deleted: {$d4}" . PHP_EOL;

    $d5 = $conn->table('source_documents')->where('id', $mathDocId)->delete();
    echo "Doc deleted: {$d5}" . PHP_EOL;

    $conn->commit();
    echo "COMMIT EXITOSO!" . PHP_EOL;

} catch (\Throwable $e) {
    $conn->rollBack();
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

