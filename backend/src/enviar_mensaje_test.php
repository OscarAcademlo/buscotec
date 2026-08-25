<?php
// backend/enviar_mensaje_test.php
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok'=>false,'error'=>'Sin conexión']);
    exit;
}

try {
    $caso_id = 0;
    $remitente_id = 10;          // usuario
    $remitente_tipo = 'usuario';
    $destinatario_id = 39;       // profesional
    $destinatario_tipo = 'profesional';
    $tipo = 'usuario';
    $id_global = bin2hex(random_bytes(8));
    $direccion = 'user_to_pro';
    $mensaje = '🧩 Test desde enviar_mensaje_test.php (10 → 39)';
    $tiene_adjuntos = 0;
    $leido = 0;
    $estado_respuesta = 'pendiente';

    $sql = "INSERT INTO mensajes (
        caso_id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo,
        tipo, id_global, direccion, mensaje, tiene_adjuntos, leido, estado_respuesta, fecha_envio
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iissssssiiis",
        $caso_id,
        $remitente_id,
        $remitente_tipo,
        $destinatario_id,
        $destinatario_tipo,
        $tipo,
        $id_global,
        $direccion,
        $mensaje,
        $tiene_adjuntos,
        $leido,
        $estado_respuesta
    );

    $stmt->execute();

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Insert realizado correctamente',
        'insert_id' => $conn->insert_id,
        'id_global' => $id_global
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
