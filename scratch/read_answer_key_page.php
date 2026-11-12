<?php

$text = file_get_contents(__DIR__ . '/pdf_extracted_raw_text.txt');
$pos = stripos($text, 'Tabla de respuestas correctas');
if ($pos !== false) {
    echo "Found at $pos:\n\n";
    echo substr($text, $pos - 200, 25000);
} else {
    echo "Not found";
}

