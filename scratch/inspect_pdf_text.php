<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;

$docId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$doc = SourceDocument::find($docId);
$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$pdfBytes = $storage->get($bucket, $doc->storage_path);

echo "PDF size: " . strlen($pdfBytes) . " bytes\n";

// Buscar texto dentro de los streams de PDF descomprimiéndolos con gzuncompress
preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $pdfBytes, $matches);
echo "Total streams found: " . count($matches[1]) . "\n";

$allText = '';
$streamTexts = [];

foreach ($matches[1] as $idx => $stream) {
    $decomp = @gzuncompress($stream);
    if ($decomp === false) {
        $decomp = @gzinflate(substr($stream, 2));
    }
    if ($decomp !== false) {
        // Extraer texto entre paréntesis (...) o entre corchetes [...] con operadores Tj, TJ, '
        preg_match_all('#\((.*?)\)\s*(?:Tj|\')#s', $decomp, $tjMatches);
        $streamStr = '';
        if (!empty($tjMatches[1])) {
            $streamStr .= implode(' ', $tjMatches[1]) . "\n";
        }
        
        // Bloques TJ
        preg_match_all('#\[(.*?)\]\s*TJ#s', $decomp, $tjArrayMatches);
        if (!empty($tjArrayMatches[1])) {
            foreach ($tjArrayMatches[1] as $arr) {
                preg_match_all('#\((.*?)\)#s', $arr, $inner);
                if (!empty($inner[1])) {
                    $streamStr .= implode('', $inner[1]) . " ";
                }
            }
            $streamStr .= "\n";
        }

        if (!empty($streamStr)) {
            $streamTexts[$idx] = $streamStr;
            $allText .= $streamStr;
        }
    }
}

echo "Extracted text length from streams: " . strlen($allText) . "\n";
file_put_contents(__DIR__ . '/pdf_extracted_raw_text.txt', $allText);
echo "Saved raw text to scratch/pdf_extracted_raw_text.txt\n";

// Buscar indicios de clave de respuestas
echo "\n--- Búsqueda de términos de respuestas en el PDF ---\n";
foreach (['clave', 'respuestas', 'tabla de respuestas', 'hoja de respuestas', 'solucionario', 'pregunta 1', 'pregunta 50'] as $term) {
    $pos = stripos($allText, $term);
    if ($pos !== false) {
        echo " - Encontrado '{$term}' en posición {$pos}:\n";
        echo "   " . substr($allText, max(0, $pos - 40), 120) . "\n";
    } else {
        echo " - '{$term}' NO encontrado\n";
    }
}

