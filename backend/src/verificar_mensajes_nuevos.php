<?php
// backend/verificar_mensajes_nuevos.php

declare(strict_types=1);
require_once __DIR__ . '/session_boot.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

$db = $conn ?? $conexion ?? null;
if (!$db || !($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Conexión no válida']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_tipo = $_SESSION['role'] ?? null; // 'usuario' o 'profesional'

if (!$user_id || !$user_tipo) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

try {
    $sql = "SELECT COUNT(*) AS total 
            FROM mensajes 
            WHERE destinatario_id = ? 
              AND destinatario_tipo = ? 
              AND leido = 0";

    if (!$stmt = $db->prepare($sql)) {
        throw new Exception("Error en prepare: " . $db->error);
    }

    $stmt->bind_param("is", $user_id, $user_tipo);
    if (!$stmt->execute()) {
        throw new Exception("Error en execute: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    echo json_encode(['ok' => true, 'nuevos' => (int) $count]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error en la consulta',
        'debug' => $e->getMessage() // 👈 podés quitar esto en producción
    ]);
}
