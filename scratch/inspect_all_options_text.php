<?php

$matrix = json_decode(file_get_contents(__DIR__ . '/audit_detailed_matrix.json'), true);

echo "=== INSPECCIÓN DE OPCIONES EN LAS 50 PREGUNTAS ===\n\n";

$placeholderOptions = [];

foreach ($matrix as $ord => $item) {
    foreach ($item['options'] as $l => $opt) {
        $t = trim($opt['text']);
        if (preg_match('/^(opci[oó]n\s+[A-D]|figura\s+[A-D]|diseño\s+[A-D]|gr[aá]fica\s+[A-D])$/iu', $t)) {
            $placeholderOptions[$ord][] = "{$l}: {$t}";
        }
    }
}

echo "Preguntas con opciones tipo etiqueta/placeholder visual ('Opción A', etc.): " . count($placeholderOptions) . "\n";
foreach ($placeholderOptions as $ord => $arr) {
    echo " - Pregunta #{$ord} (Pág. {$matrix[$ord]['page']}): " . implode(', ', $arr) . "\n";
    echo "   Enunciado: " . mb_substr($matrix[$ord]['statement'], 0, 100) . "...\n";
}

