<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;

$doc = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$pdfBytes = $storage->get($bucket, $doc->storage_path);

preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $pdfBytes, $matches);
$allText = '';
foreach ($matches[1] as $stream) {
    $decomp = @gzuncompress($stream);
    if ($decomp === false) $decomp = @gzinflate(substr($stream, 2));
    if ($decomp !== false) $allText .= $decomp . "\n";
}

$rawText = '';
foreach ($matches[1] as $stream) {
    $decomp = @gzuncompress($stream);
    if ($decomp === false) $decomp = @gzinflate(substr($stream, 2));
    if ($decomp !== false) {
        preg_match_all('#\((.*?)\)\s*(?:Tj|\')#s', $decomp, $tjMatches);
        if (!empty($tjMatches[1])) {
            $rawText .= implode(' ', $tjMatches[1]) . "\n";
        }
        preg_match_all('#\[(.*?)\]\s*TJ#s', $decomp, $tjArrayMatches);
        if (!empty($tjArrayMatches[1])) {
            foreach ($tjArrayMatches[1] as $arr) {
                preg_match_all('#\((.*?)\)#s', $arr, $inner);
                if (!empty($inner[1])) {
                    $rawText .= implode('', $inner[1]) . " ";
                }
            }
            $rawText .= "\n";
        }
    }
}

$pos = stripos($rawText, 'Tabla de respuestas correctas');
echo "Posición tabla: " . ($pos !== false ? $pos : 'NO ENCONTRADA') . "\n";

if ($pos !== false) {
    $sub = substr($rawText, $pos, 40000);
    preg_match_all('/\((\d{1,3})\s+es-ES\).*?\(([A-Da-d])[\s\r\n]*es-ES/s', $sub, $parsed, PREG_SET_ORDER);
    $keys = [];
    foreach ($parsed as $p) {
        $num = (int)$p[1];
        if ($num >= 1 && $num <= 200) {
            $keys[$num] = strtoupper(trim($p[2]));
        }
    }
    ksort($keys);
    echo "Total claves parseadas: " . count($keys) . "\n";
    echo "Claves (muestra 1..10): " . json_encode(array_slice($keys, 0, 10, true)) . "\n";
}

