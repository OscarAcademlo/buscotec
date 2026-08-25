<?php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['id'] ?? 0);
$role   = $_SESSION['role'] ?? '';

if ($userId <= 0 || $role !== 'profesional') {
    echo json_encode(['ok' => false, 'error' => 'Solo profesionales pueden responder']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$mensajeId = (int)($data['mensaje_id'] ?? 0);
$accion    = $data['accion'] ?? '';

if (!$mensajeId || !in_array($accion, ['aceptar','rechazar'])) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

$estado = ($accion === 'aceptar') ? 'aceptado' : 'rechazado';

$stmt = $conn->prepare("UPDATE mensajes SET estado_respuesta=? WHERE id=? AND destinatario_id=?");
$stmt->bind_param("sii", $estado, $mensajeId, $userId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok, 'estado' => $estado]);
$conn->close();
