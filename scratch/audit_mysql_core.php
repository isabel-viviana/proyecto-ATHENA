<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=Athena;charset=utf8mb4', 'root', '');

echo "=== TABLES IN MYSQL Athena ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "- $t\n";
}

echo "\n=== COLUMNS IN 'users' ===\n";
$cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['Field']} ({$c['Type']}) " . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " " . ($c['Default'] ? "DEFAULT {$c['Default']}" : "") . "\n";
}

if (in_array('notifications', $tables)) {
    echo "\n=== COLUMNS IN 'notifications' ===\n";
    $ncols = $pdo->query("DESCRIBE notifications")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ncols as $c) {
        echo "  {$c['Field']} ({$c['Type']})\n";
    }
}

// Check for gamification / ranking tables
$gamificationTables = preg_grep('/gam|rank|point|xp|coin|logr|achiev/i', $tables);
echo "\n=== GAMIFICATION / RANKING RELATED TABLES ===\n";
print_r($gamificationTables);

