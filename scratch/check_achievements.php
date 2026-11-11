<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=Athena;charset=utf8mb4', 'root', '');
echo "COLUMNS OF achievements:\n";
print_r($pdo->query('DESCRIBE achievements')->fetchAll(PDO::FETCH_ASSOC));
echo "\nDATA OF achievements:\n";
print_r($pdo->query('SELECT * FROM achievements')->fetchAll(PDO::FETCH_ASSOC));
