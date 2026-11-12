<?php

$text = file_get_contents(__DIR__ . '/pdf_extracted_raw_text.txt');
$pos = stripos($text, 'Tabla de respuestas correctas');

// Vamos a extraer de forma precisa las parejas número de pregunta y letra de respuesta
// Busquemos en los streams después de posición $pos
$sub = substr($text, $pos, 15000);

preg_match_all('/\((\d+)\s+es-ES\).*?\((\w)\s+es-ES\)/s', $sub, $matches, PREG_SET_ORDER);

$officialKeys = [];
foreach ($matches as $m) {
    $num = (int)$m[1];
    $let = strtoupper(trim($m[2]));
    if ($num >= 1 && $num <= 50) {
        $officialKeys[$num] = $let;
    }
}

ksort($officialKeys);
echo "Total official keys parsed: " . count($officialKeys) . "\n";
print_r($officialKeys);

file_put_contents(__DIR__ . '/official_keys.json', json_encode($officialKeys, JSON_PRETTY_PRINT));

