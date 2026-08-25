<?php
// test_notif.php
header('Content-Type: application/json');

// Claves REST API (privadas, del dashboard Webpushr)
$http_header = [
    "Content-Type: Application/Json",
    "webpushrKey: 4d4a588e817b482187cee2c9dfb64aec",
    "webpushrAuthToken: 114560"
];

// Payload → notificación de prueba
$req_data = [
    'title'      => "Buscotec",
    'message'    => "🚀 Test push directo desde PHP",
    'target_url' => "https://buscotec.click"
];

// Enviar notificación
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
curl_setopt($ch, CURLOPT_URL, "https://api.webpushr.com/v1/notification/send/all");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'ok' => $httpcode === 200,
    'httpcode' => $httpcode,
    'response' => json_decode($response, true)
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
