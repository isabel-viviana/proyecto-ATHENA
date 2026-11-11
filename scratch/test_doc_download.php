<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use App\Services\PdfImageExtractor;

$doc = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
if (!$doc) {
    die("Doc not found");
}

echo "Found document: " . $doc->title . "\n";
echo "Storage path: " . $doc->storage_path . "\n";

$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
echo "Bucket: $bucket\n";

$exists = $storage->exists($bucket, $doc->storage_path);
echo "Exists in Supabase Storage? " . ($exists ? 'YES' : 'NO') . "\n";

if ($exists) {
    $bytes = $storage->get($bucket, $doc->storage_path);
    $size = strlen($bytes);
    echo "Downloaded size: " . $size . " bytes (" . round($size / 1024 / 1024, 2) . " MB)\n";
    echo "PDF header check: " . substr($bytes, 0, 5) . "\n";

    // Test image extraction step
    echo "\nTesting PdfImageExtractor on this PDF...\n";
    $extractor = app(PdfImageExtractor::class);
    $result = $extractor->extract($bytes);
    echo "PdfImageExtractor total pages: " . ($result['pages'] ?? 0) . "\n";
    echo "PdfImageExtractor total images detected: " . count($result['images'] ?? []) . "\n";
    foreach ($result['images'] as $idx => $img) {
        echo " - Img $idx: Page {$img['page']}, Obj {$img['object']}, {$img['width']}x{$img['height']}, {$img['mime']}, " . strlen($img['bytes']) . " bytes\n";
    }
}

