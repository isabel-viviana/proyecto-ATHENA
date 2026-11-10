<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=Athena', 'root', '');
$stmt = $pdo->query('SELECT password FROM users WHERE id=1');
$p = $stmt->fetchColumn();
echo password_verify('password', $p) ? "MATCH: password\n" : "NO MATCH\n";

