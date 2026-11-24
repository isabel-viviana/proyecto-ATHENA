<?php
$token = '8|35ev5yX6wBdftDJU2aEhSn5IKTw33YtSM2EU4XJg6a56e8c0';
$ch = curl_init('http://127.0.0.1:8000/api/exams/21/answer');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'question_id' => 76,
    'selected_option_id' => 301,
    'time_spent_seconds' => 15
]));
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: $code\nRes: $res\n";
