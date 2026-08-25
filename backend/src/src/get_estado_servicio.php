<?php
// backend/get_estado_servicio.php
declare(strict_types=1);
require_once __DIR__ . "/session_boot.php";
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

$resp = ['ok' => false];

// Verificamos que esté logueado y tenga el rol de profesional
$profId = $_SESSION['role_ids']['profesional'] ?? null;

if (!$profId) {
    echo json_encode(['ok' => false, 'error' => 'Acceso no autorizado o no es profesional']);
    exit;
}

try {
    $db = $conn ?? $conexion ?? null;
    if (!$db || !$db instanceof mysqli)
        throw new Exception('Sin conexión');

    $stmt = $db->prepare("SELECT estado_servicio FROM profesionales WHERE id = ?");
    $stmt->bind_param("i", $profId);
    $stmt->execute();
    $res = $stmt->get_result();
    $estado = $res->fetch_assoc()['estado_servicio'] ?? 0;

    echo json_encode(['ok' => true, 'estado' => (int) $estado]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al consultar estado']);
}
