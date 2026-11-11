<?php

$questions = json_decode(file_get_contents(__DIR__ . '/math_questions_dump.json'), true);

echo "=== PREGUNTAS CON ENUNCIADOS CORTOS (< 180 CARACTERES) ===\n\n";

$shortList = [];

foreach ($questions as $q) {
    $len = mb_strlen(trim($q['statement']));
    if ($len < 200) {
        $shortList[] = [
            'order' => $q['order'],
            'page' => $q['page'],
            'length' => $len,
            'stimulus' => $q['stimulus_title'] ?? 'NINGUNO',
            'statement' => $q['statement'],
        ];
    }
}

echo "Total preguntas con enunciado < 200 caracteres: " . count($shortList) . "\n\n";

foreach ($shortList as $item) {
    echo "Pregunta #{$item['order']} (Pág. {$item['page']}, Stimulus: {$item['stimulus']}, {$item['length']} chars):\n";
    echo "  " . $item['statement'] . "\n\n";
}

