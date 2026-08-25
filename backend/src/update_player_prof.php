<?php
// backend/update_player_prof.php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Seguridad extra
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $playerId = isset($data['player_id']) ? trim($data['player_id']) : null;
    $profId   = isset($data['prof_id']) ? intval($data['prof_id']) : null;

    // Si no se pasó el prof_id, usamos el de la sesión (por si ya está logueado)
    if (!$profId && isset($_SESSION['user_id'])) {
        $profId = (int) $_SESSION['user_id'];
    }

    if (!$playerId || !$profId) {
        echo json_encode(['ok' => false, 'error' => 'Falta player_id o prof_id']);
        exit;
    }

    $conn->set_charset("utf8mb4");

    // --- 1. Eliminar cualquier token anterior de este profesional ---
    if ($stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ?")) {
        $stmt->bind_param("i", $profId);
        $stmt->execute();
        $stmt->close();
    }

    // --- 2. Insertar el nuevo token ---
    $stmt = $conn->prepare("INSERT INTO push_subscriptions (user_id, token, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("is", $profId, $playerId);
    $ok = $stmt->execute();
    if (!$ok) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    echo json_encode([
        'ok'       => true,
        'playerId' => $playerId,
        'profId'   => $profId
    ]);
} catch (Throwable $e) {
    error_log("[update_player_prof] " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
