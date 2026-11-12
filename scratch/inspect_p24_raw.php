<?php

$text = file_get_contents(__DIR__ . '/pdf_extracted_raw_text.txt');
$pos = strpos($text, 'Pregunta 24 ');
if ($pos !== false) {
    echo "Around Pregunta 24 in raw text:\n\n";
    echo substr($text, $pos - 1500, 3000);
} else {
    echo "Pregunta 24 not found";
}

