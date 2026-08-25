<?php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

// --- Usuario logueado ---
$userId = $_SESSION['user_id'] ?? 0;
$token  = $_POST['token'] ?? '';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}
if (!$token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Token vacío']);
    exit;
}

// --- Guardar token evitando duplicados ---
try {
    // Si ya existe combinación user_id + token, la reemplaza
    $stmt = $conn->prepare("REPLACE INTO push_subscriptions (user_id, token) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception("Error prepare: " . $conn->error);
    }
    $stmt->bind_param("is", $userId, $token);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => $ok, 'token' => $token]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
