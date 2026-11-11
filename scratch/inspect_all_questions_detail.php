<?php

$questions = json_decode(file_get_contents(__DIR__ . '/math_questions_dump.json'), true);
$officialKeys = json_decode(file_get_contents(__DIR__ . '/official_keys.json'), true);

echo "=================================================================\n";
echo "AUDITORÍA DETALLADA: 50 PREGUNTAS MATEMÁTICAS 2026\n";
echo "=================================================================\n\n";

$auditResults = [];

foreach ($questions as $q) {
    $order = $q['order'];
    $page = $q['page'];
    $stmt = $q['statement'];
    $options = $q['options'];
    $offKey = $officialKeys[$order] ?? null;
    
    // Buscar opción marcada
    $markedKey = null;
    foreach ($options as $l => $opt) {
        if ($opt['is_correct']) {
            $markedKey = $l;
            break;
        }
    }

    $issues = [];
    $visualAlert = null;
    $mathItems = [];

    // Validar concordancia de clave
    if ($markedKey !== $offKey) {
        $issues[] = "DISCREPANCIA CLAVE: Almacenada={$markedKey}, Oficial={$offKey}";
    }

    // Validar opciones
    $optsCount = count($options);
    if ($optsCount !== 4) {
        $issues[] = "Opciones incompletas: {$optsCount} en vez de 4";
    }
    foreach (['A', 'B', 'C', 'D'] as $l) {
        if (!isset($options[$l]) || trim($options[$l]['text']) === '') {
            $issues[] = "Opción {$l} vacía o faltante";
        }
    }

    // Identificar si la pregunta hace referencia a una figura gráfica visual que podría requerir imagen
    if (preg_match('/\b(en la figura|de acuerdo con la figura|la gráfica muestra|en el gráfico|el diagrama|en el plano|el mapa|la ilustración)\b/iu', $stmt, $m)) {
        $visualAlert = "Referencia visual explícita en enunciado: '{$m[1]}'";
    }

    // Verificar longitud y completitud del enunciado
    if (mb_strlen($stmt) < 30) {
        $issues[] = "Enunciado sospechosamente corto (" . mb_strlen($stmt) . " caracteres)";
    }

    $auditResults[$order] = [
        'order' => $order,
        'page' => $page,
        'statement' => $stmt,
        'official_key' => $offKey,
        'stored_key' => $markedKey,
        'options' => $options,
        'stimulus' => $q['stimulus_title'] ?? null,
        'visual_alert' => $visualAlert,
        'issues' => $issues,
    ];
}

// Resumen de auditoría
$cleanCount = 0;
$withVisualCount = 0;
$withIssuesCount = 0;

foreach ($auditResults as $ord => $res) {
    if (empty($res['issues']) && empty($res['visual_alert'])) {
        $cleanCount++;
    }
    if (!empty($res['visual_alert'])) {
        $withVisualCount++;
    }
    if (!empty($res['issues'])) {
        $withIssuesCount++;
    }
}

echo "RESUMEN:\n";
echo " - Preguntas 100% íntegras (texto, 4 opciones, clave oficial verificada): {$cleanCount}\n";
echo " - Preguntas con referencia a figuras/gráficas visuales: {$withVisualCount}\n";
echo " - Preguntas con anomalías de opciones o claves: {$withIssuesCount}\n\n";

file_put_contents(__DIR__ . '/audit_detailed_matrix.json', json_encode($auditResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "Matriz completa guardada en scratch/audit_detailed_matrix.json\n";

