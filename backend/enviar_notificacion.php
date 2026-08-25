<?php
// enviar_notificacion.php
header('Content-Type: application/json; charset=utf-8');

// Leer payload del fetch
$data = json_decode(file_get_contents("php://input"), true);

$sid   = $data['sid']   ?? null;   // subscriber_id Webpushr
$title = $data['title'] ?? 'Buscotec';
$msg   = $data['message'] ?? '📩 Nuevo mensaje';
$url   = $data['url']   ?? 'https://www.buscotec.com.ar';

// Validar
if (!$sid) {
    echo json_encode(['ok' => false, 'error' => '❌ Falta el subscriberId (sid)']);
    exit;
}

// Config Webpushr
$http_header = [
    "Content-Type: Application/Json",
    "webpushrKey: 8d266ba235785924bb013777554cbf67",
    "webpushrAuthToken: 120281"
];

// Payload para Webpushr
$req_data = [
    "title"      => $title,
    "message"    => $msg,
    "target_url" => $url,
    "sid"        => (string)$sid // Debe ser string
];

// Enviar a Webpushr
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
curl_setopt($ch, CURLOPT_URL, "https://api.webpushr.com/v1/notification/send/sid");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Respuesta final
echo json_encode([
    "ok"       => $httpcode == 200,
    "httpcode" => $httpcode,
    "sent_to"  => $sid,
    "payload"  => $req_data,
    "response" => json_decode($response, true)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
