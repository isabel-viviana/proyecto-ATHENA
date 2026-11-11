<?php

$text = file_get_contents(__DIR__ . '/pdf_extracted_raw_text.txt');
$pos = stripos($text, 'Tabla de respuestas correctas');
$sub = substr($text, $pos, 25000);

// Buscar 23, 24, 25
$p24 = strpos($sub, '(24 ');
echo "Around 24:\n" . substr($sub, $p24 - 50, 400) . "\n\n";

$p25 = strpos($sub, '(25 ');
echo "Around 25:\n" . substr($sub, $p25 - 50, 400) . "\n\n";

$p50 = strpos($sub, '(50 ');
if ($p50 !== false) {
    echo "Around 50:\n" . substr($sub, $p50 - 50, 400) . "\n\n";
} else {
    echo "50 not found with (50 \n";
    // buscar 49
    $p49 = strpos($sub, '(49 ');
    echo "Around 49:\n" . substr($sub, $p49 - 50, 800) . "\n\n";
}

