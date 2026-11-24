<?php
$token = '10|tTisTqgINiDyj15CzUekjm19C36zUTFJ8arXACvr4ad1ac75';
$ch = curl_init('http://127.0.0.1:8000/api/ai/message');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'message' => 'Hola Zorro ATHENA'
]));
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: $code\nRes: $res\n";
