<?php

$mysqli = @new mysqli('127.0.0.1', 'root', '', 'Athena', 3306);

if ($mysqli->connect_error) {
    echo "Error conectando a MySQL: " . $mysqli->connect_error . PHP_EOL;
    exit;
}

echo "=== TABLAS EN MYSQL (DATABASE: Athena) ===" . PHP_EOL;
$res = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

echo "Total tablas encontradas: " . count($tables) . PHP_EOL;
foreach ($tables as $t) {
    $cntRes = $mysqli->query("SELECT COUNT(*) FROM `{$t}`");
    $cnt = $cntRes ? $cntRes->fetch_row()[0] : 'N/A';
    echo "  - {$t} ({$cnt} registros)" . PHP_EOL;
}

$mysqli->close();

