<?php
$pdoMy = new PDO('mysql:host=127.0.0.1;dbname=Athena;charset=utf8mb4', 'root', '');

echo "=== COLUMNS IN MySQL Athena ===\n";
foreach (['exam_configs', 'exam_config_subjects', 'exam_attempts', 'attempt_answers'] as $tbl) {
    echo "\n--- Table: $tbl ---\n";
    $stmt = $pdoMy->query("DESCRIBE $tbl");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo "  {$c['Field']} ({$c['Type']}) " . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " " . ($c['Key'] ? "KEY:{$c['Key']}" : "") . "\n";
    }
}

