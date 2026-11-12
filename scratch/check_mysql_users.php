<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=Athena;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT id, full_name, email, role, password FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Users in MySQL Athena:\n";
foreach ($users as $u) {
    echo "- ID: {$u['id']} | Name: {$u['full_name']} | Email: {$u['email']} | Role: {$u['role']} | PassHash: " . substr($u['password'], 0, 15) . "...\n";
}

