<?php

$text = file_get_contents(__DIR__ . '/pdf_extracted_raw_text.txt');

for ($i = 1; $i <= 50; $i++) {
    $pos = strpos($text, "Pregunta {$i} ");
    if ($pos !== false) {
        $snippet = substr($text, $pos, 300);
        // Quitar etiquetas PDF como /MCID, BDC, BT, etc.
        $clean = preg_replace('/\/[A-Za-z0-9_]+|>>BDC|EMC|BT|ET|\d+\s+\d+\s+Tm|\d+\s+Tf|\bTc\b|\bTw\b|\bscn\b|\bgs\b/s', '', $snippet);
        $clean = preg_replace('/\s+/', ' ', $clean);
        echo "P{$i}: " . mb_substr(trim($clean), 0, 100) . "\n";
    } else {
        echo "P{$i}: NO ENCONTRADA EN PDF RAW\n";
    }
}
