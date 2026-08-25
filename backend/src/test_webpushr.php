<?php
// backend/test_webpushr.php
header('Content-Type: text/plain; charset=utf-8');

$WEBPUSHR_KEY        = '4d4a588e817b482187cee2c9dfb64aec';
$WEBPUSHR_AUTH_TOKEN = '114560';
$WEBPUSHR_ENDPOINT   = 'https://api.webpushr.com/v1/notification/send/sid';

// ✅ ID del usuario que debe recibir el push
$subscriber_id = '186608392'; // ← este es el player_id del usuario 10

$payload = [
    "title"      => "📢 Test directo Webpushr",
    "message"    => "Notificación directa enviada desde PHP 💬",
    "target_url" => "https://buscotec.com.ar/",
    "sid"        => (string)$subscriber_id
];

$headers = [
    "Content-Type: Application/Json",
    "webpushrKey: {$WEBPUSHR_KEY}",
    "webpushrAuthToken: {$WEBPUSHR_AUTH_TOKEN}"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $WEBPUSHR_ENDPOINT);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http\n";
echo "cURL Error: $error\n";
echo "Response: $response\n";
