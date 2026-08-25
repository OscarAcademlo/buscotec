<?php
// backend/test_push_profesional.php
header('Content-Type: application/json; charset=utf-8');

$apiKey = getenv('ONESIGNAL_REST_KEY') ?: "YOUR_REST_API_KEY_HERE"; // tu REST API KEY
$appId  = "de693ded-f0e5-4a55-bfca-cdf2e1313af5";          // tu APP ID

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(["ok"=>false,"error"=>"Falta id"]);
    exit;
}

require_once __DIR__ . "/conexion.php";

// Buscar todos los playerId de ese profesional
$stmt = $conn->prepare("SELECT player_id FROM push_subscriptions WHERE profesional_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$playerIds = [];
while ($row = $res->fetch_assoc()) {
    $playerIds[] = $row['player_id'];
}
$stmt->close();

if (!$playerIds) {
    echo json_encode(["ok"=>false,"error"=>"Profesional sin playerIds"]);
    exit;
}

$payload = [
    'app_id' => $appId,
    'include_player_ids' => $playerIds,
    'headings' => [ "es" => "BuscoTec", "en" => "BuscoTec" ],
    'contents' => [ "es" => "📩 ¡Tienes un mensaje nuevo en BuscoTec!", "en" => "📩 You have a new message in BuscoTec!" ],
    'url' => "https://buscotec.click/mensajes.html",
    'chrome_web_icon' => "https://buscotec.click/img/icons/icon-192.png"
];

// Enviar a OneSignal
$ch = curl_init("https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charset=utf-8',
    'Authorization: Basic ' . $apiKey
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resp = json_decode($response, true);

// 🔥 Si OneSignal devuelve invalid_player_ids, borrarlos de DB
if (isset($resp['errors']['invalid_player_ids'])) {
    $invalids = $resp['errors']['invalid_player_ids'];
    $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE player_id=?");
    foreach ($invalids as $bad) {
        $stmt->bind_param("s", $bad);
        $stmt->execute();
    }
    $stmt->close();
}

echo json_encode([
    "ok" => $http === 200,
    "http" => $http,
    "resp" => $resp,
    "enviados" => $playerIds
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
