<?php
// test_envio.php
$endpoint = 'https://api.webpushr.com/v1/notification/send/sid';
$http_header = array(
    "Content-Type: Application/Json",
    "webpushrKey: 4d4a588e817b482187cee2c9dfb64aec",
    "webpushrAuthToken: 114560"
);

$req_data = array(
    "title"      => "Buscotec",
    "message"    => "📢 Hola directo a la tablet!",
    "target_url" => "https://buscotec.click",
    "sid"        => "186604700" // 👉 tu tablet Android
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    "ok"       => $httpcode == 200,
    "httpcode" => $httpcode,
    "payload"  => $req_data,
    "response" => json_decode($response, true)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
