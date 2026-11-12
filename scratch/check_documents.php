<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;

$docs = SourceDocument::orderBy('created_at', 'desc')->get();

echo "Total documents in source_documents: " . $docs->count() . "\n\n";

foreach ($docs as $doc) {
    echo "ID: " . $doc->id . "\n";
    echo "Title: " . $doc->title . "\n";
    echo "Year: " . $doc->year . " | Exam Type: " . $doc->exam_type . "\n";
    echo "Status: " . $doc->status . "\n";
    echo "Storage Path: " . $doc->storage_path . "\n";
    echo "Created At: " . $doc->created_at . "\n";
    echo "Extraction Metadata:\n" . json_encode($doc->extraction_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    echo "---------------------------------------------------------\n";
}

