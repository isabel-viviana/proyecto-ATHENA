<?php

$matrix = json_decode(file_get_contents(__DIR__ . '/audit_detailed_matrix.json'), true);

echo "=== PREGUNTAS CON REFERENCIAS VISUALES (FIGURA / GRÁFICA / DIAGRAMA) ===\n\n";

foreach ($matrix as $ord => $item) {
    if (!empty($item['visual_alert'])) {
        echo "Pregunta #{$ord} (Página PDF: {$item['page']}):\n";
        echo " - Alerta: {$item['visual_alert']}\n";
        echo " - Clave Oficial: {$item['official_key']}\n";
        echo " - Enunciado:\n   " . $item['statement'] . "\n";
        echo " - Opciones:\n";
        foreach ($item['options'] as $l => $opt) {
            $check = $opt['is_correct'] ? ' [CORRECTA OFICIAL]' : '';
            echo "   {$l}) {$opt['text']}{$check}\n";
        }
        echo "-----------------------------------------------------------------\n";
    }
}

