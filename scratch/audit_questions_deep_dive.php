<?php

$questions = json_decode(file_get_contents(__DIR__ . '/math_questions_dump.json'), true);
$rawPdfText = file_get_contents(__DIR__ . '/pdf_extracted_raw_text.txt');

echo "=== ANÁLISIS DETALLADO DE LAS 50 PREGUNTAS ===\n\n";

$visualReferences = [];
$mathFormulas = [];
$shortOptions = [];
$potentialTruncations = [];
$stimuliGroups = [];

foreach ($questions as $q) {
    $order = $q['order'];
    $page = $q['page'];
    $stmt = $q['statement'];
    $options = $q['options'];
    
    // 1. Referencias visuales en el enunciado o en las opciones
    $hasVisualWord = false;
    $visualKeywords = ['figura', 'gráfica', 'grafica', 'diagrama', 'tabla', 'imagen', 'dibujo', 'plano cartesiano', 'esquema', 'mapa'];
    foreach ($visualKeywords as $kw) {
        if (stripos($stmt, $kw) !== false) {
            $hasVisualWord = true;
            $visualReferences[$order] = [
                'page' => $page,
                'keyword' => $kw,
                'snippet' => mb_substr($stmt, 0, 140),
            ];
            break;
        }
    }

    // 2. Fórmulas / Expresiones matemáticas
    $hasMath = false;
    $mathPatterns = ['/x\^?\d/', '/\^/', '/\=/', '/\%/', '/\$/', '/cm\^?[23]?/', '/m\^?[23]?/', '/\bkm\b/', '/\bpi\b/i', '/\braíz\b/i', '/\bsqrt\b/i', '/\b\d+[\+\-\*\/]\d+\b/'];
    foreach ($mathPatterns as $pat) {
        if (preg_match($pat, $stmt)) {
            $hasMath = true;
            break;
        }
    }
    if ($hasMath) {
        $mathFormulas[$order] = $page;
    }

    // 3. Opciones cortas o sospechosas de truncamiento
    foreach ($options as $letter => $opt) {
        $text = trim($opt['text']);
        if (strlen($text) < 2) {
            $shortOptions[$order][] = "{$letter}: '{$text}'";
        }
        // Detectar si termina abruptamente sin punto ni cierre o con elipsis
        if (preg_match('/\.\.\.$|…$/', $text)) {
            $potentialTruncations[$order][] = "{$letter}: '{$text}'";
        }
    }

    // 4. Estímulos
    if (!empty($q['stimulus_id'])) {
        $stimuliGroups[$q['stimulus_title'] ?? 'Sin título'][] = $order;
    }
}

echo "1. Preguntas con referencias a tablas, gráficas, figuras o diagramas: " . count($visualReferences) . "\n";
echo "   Órdenes: " . implode(', ', array_keys($visualReferences)) . "\n\n";

echo "2. Preguntas con expresiones matemáticas / fórmulas / unidades: " . count($mathFormulas) . "\n";
echo "   Órdenes: " . implode(', ', array_keys($mathFormulas)) . "\n\n";

echo "3. Preguntas con opciones extremadamente cortas (< 2 caracteres): " . count($shortOptions) . "\n";
foreach ($shortOptions as $ord => $arr) {
    echo "   - Pregunta #{$ord}: " . implode(', ', $arr) . "\n";
}
if (empty($shortOptions)) echo "   (Ninguna)\n\n";

echo "4. Preguntas con posible truncamiento en opciones (...): " . count($potentialTruncations) . "\n";
foreach ($potentialTruncations as $ord => $arr) {
    echo "   - Pregunta #{$ord}: " . implode(', ', $arr) . "\n";
}
if (empty($potentialTruncations)) echo "   (Ninguna)\n\n";

echo "5. Agrupaciones de Estímulos Compartidos:\n";
foreach ($stimuliGroups as $title => $ords) {
    echo "   - '{$title}': Preguntas " . implode(', ', $ords) . "\n";
}
echo "\n";

