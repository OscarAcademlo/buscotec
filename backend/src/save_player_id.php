<?php
// backend/save_player_id.php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['id'] ?? 0;
$role   = $_SESSION['role'] ?? null;
$input  = json_decode(file_get_contents("php://input"), true);
$playerId = $input['playerId'] ?? null;

if (!$userId || !$role) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}
if (!$playerId) {
    echo json_encode(['ok' => false, 'error' => 'Falta playerId']);
    exit;
}

$table = $role === 'profesional' ? 'profesionales' : 'usuarios';
$stmt = $conn->prepare("UPDATE $table SET onesignal_id=? WHERE id=?");
$stmt->bind_param("si", $playerId, $userId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok, 'playerId' => $playerId]);
