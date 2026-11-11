<?php

$official = json_decode(file_get_contents(__DIR__ . '/official_keys.json'), true);
$extracted = json_decode(file_get_contents(__DIR__ . '/math_questions_dump.json'), true);

echo "=== COMPARACIÓN DE CLAVES: OFICIAL DEL PDF VS EXTRAÍDA POR GEMINI ===\n\n";

$matches = 0;
$mismatches = 0;
$noKey = 0;

$mismatchList = [];

foreach ($extracted as $q) {
    $order = $q['order'];
    $offKey = $official[$order] ?? null;
    
    // Buscar cuál opción tiene is_correct = true en las opciones extraídas
    $extKey = null;
    foreach ($q['options'] as $letter => $opt) {
        if ($opt['is_correct'] === true) {
            $extKey = $letter;
            break;
        }
    }

    if ($extKey === null) {
        $noKey++;
        echo "Pregunta #{$order}: Oficial = {$offKey} | Extraída = NINGUNA (is_correct null)\n";
    } elseif ($extKey === $offKey) {
        $matches++;
    } else {
        $mismatches++;
        $mismatchList[] = [
            'order' => $order,
            'page' => $q['page'],
            'official' => $offKey,
            'extracted' => $extKey,
            'statement' => mb_substr($q['statement'], 0, 80),
        ];
        echo "Pregunta #{$order} (Pág. {$q['page']}): DISCREPANCIA -> Oficial = {$offKey} | Gemini extrajo = {$extKey}\n";
    }
}

echo "\nResumen:\n";
echo " - Coincidencias exactas con la clave oficial: {$matches} / 50\n";
echo " - Discrepancias: {$mismatches} / 50\n";
echo " - Sin clave extraída: {$noKey} / 50\n";

