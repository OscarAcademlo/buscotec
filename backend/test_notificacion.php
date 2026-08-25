<?php
header('Content-Type: application/json');

$WEBPUSHR_KEY = "BBYWfbyZlACLw6GfRLiIAu_uZGTTBLb05Nf6-3qO3mQuMY1IYlUYqeLTwFvOLKDC6nIsg8BCeKEquGj7dwE_mhU";
$WEBPUSHR_AUTH_TOKEN = "8b4235e52df801e68a939007760296d8";

// Usa exactamente un ID de tu panel Webpushr (como string)
$subscriberId = "186564253";

$data = [
    "title"          => "🔔 Test directo",
    "message"        => "Si ves esto, el push funciona",
    "target_url"     => "https://buscotec.click/",
    "subscriber_ids" => [ (string)$subscriberId ]   // 👈 fuerza a string
];

$ch = curl_init("https://api.webpushr.com/v1/notification/send");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "webpushrKey: $WEBPUSHR_KEY",
    "webpushrAuthToken: $WEBPUSHR_AUTH_TOKEN"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    "http_code" => $httpcode,
    "payload"   => $data,
    "response"  => $response
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
