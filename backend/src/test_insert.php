<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Datos de prueba
$userId = 11; // cambia al id de un profesional real que tengas
$playerId = "TEST_" . uniqid(); 
$navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';
$ahora = date('Y-m-d H:i:s');

try {
    $sql = "INSERT INTO suscripciones_push (usuario_id, player_id, navegador, fecha_creacion, fecha_actualizacion, activo)
            VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error prepare: " . $conn->error);
    }
    $stmt->bind_param("issss", $userId, $playerId, $navegador, $ahora, $ahora);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode([
        "ok" => $ok,
        "id_insertado" => $conn->insert_id,
        "user_id" => $userId,
        "player_id" => $playerId
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
}
