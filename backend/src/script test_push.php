<?php
// backend/test_push.php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

// --- Config OneSignal ---
$APP_ID   = getenv('ONESIGNAL_APP_ID') ?: "de693ded-f0e5-4a55-bfca-cdf2e1313af5";
$REST_KEY = getenv('ONESIGNAL_REST_KEY') ?: "YOUR_ONESIGNAL_REST_KEY_HERE";

// --- Parametros ---
$profId = (int)($_GET['prof_id'] ?? 0);
if (!$profId) {
    echo json_encode(['ok'=>false,'error'=>'Falta prof_id']);
    exit;
}

// --- Buscar token del profesional ---
$stmt = $conn->prepare("SELECT token FROM push_subscriptions WHERE user_id = ? AND revoked_at IS NULL ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $profId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['ok'=>false,'error'=>'No hay token válido para este profesional']);
    exit;
}
$playerId = $row['token'];

// --- Armar payload ---
$fields = [
    'app_id' => $APP_ID,
    'include_player_ids' => [$playerId],
    'headings' => ["es" => "📢 Test Notificación"],
    'contents' => ["es" => "Hola profe #$profId, esta es una prueba de BuscoTec ✅"],
    'priority' => 10
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charset=utf-8',
    "Authorization: Basic $REST_KEY"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$pushResult = json_decode($response, true);

// --- Limpieza tokens inválidos ---
if (!empty($pushResult['errors']['invalid_player_ids'])) {
    foreach ($pushResult['errors']['invalid_player_ids'] as $badId) {
        $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE token = ?");
        $stmt->bind_param("s", $badId);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Respuesta final ---
echo json_encode([
    'ok'        => true,
    'prof_id'   => $profId,
    'player_id' => $playerId,
    'http_code' => $httpCode,
    'onesignal_response' => $pushResult
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
