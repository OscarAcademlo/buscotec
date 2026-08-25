<?php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . "/conexion.php";
header("Content-Type: application/json; charset=utf-8");

$data = json_decode(file_get_contents("php://input"), true);
$playerId = $data['player_id'] ?? null;

$role = $_SESSION['role'] ?? null;   // 'usuario' o 'profesional'
$id   = $_SESSION['id'] ?? null;

if (!$id || !$playerId || !$role) {
    echo json_encode([
        "ok" => false,
        "error" => "Faltan datos: id, role o player_id"
    ]);
    exit;
}

if ($role === "usuario") {
    $sql = "UPDATE usuarios SET onesignal_id=? WHERE id=?";
} elseif ($role === "profesional") {
    $sql = "UPDATE profesionales SET onesignal_id=? WHERE id=?";
} else {
    echo json_encode([
        "ok" => false,
        "error" => "Rol inválido"
    ]);
    exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $playerId, $id);
$ok = $stmt->execute();

echo json_encode([
    "ok" => $ok,
    "role" => $role,
    "id" => $id,
    "player_id" => $playerId
]);
