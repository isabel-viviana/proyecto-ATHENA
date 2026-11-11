<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=Athena;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT * FROM notifications');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total notifications in MySQL: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "- [ID: {$r['id']}, User: {$r['user_id']}, Type: {$r['type']}, Read: {$r['is_read']}] {$r['title']}: {$r['message']} ({$r['created_at']})\n";
}

