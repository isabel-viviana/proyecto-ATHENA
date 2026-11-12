<?php

$matrix = json_decode(file_get_contents(__DIR__ . '/audit_detailed_matrix.json'), true);

echo "PREGUNTA 23:\n";
print_r($matrix[23]);

echo "\nPREGUNTA 24:\n";
print_r($matrix[24]);

