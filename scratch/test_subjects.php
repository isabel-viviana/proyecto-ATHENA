<?php
$token = '8|35ev5yX6wBdftDJU2aEhSn5IKTw33YtSM2EU4XJg6a56e8c0';
$ch = curl_init('http://127.0.0.1:8000/api/subjects');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: $code\nRes: $res\n";
