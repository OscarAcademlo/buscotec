<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: text/plain; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $conn->set_charset('utf8mb4');

    $sql = "INSERT INTO mensajes
            (caso_id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo, direccion, mensaje, tiene_adjuntos, leido, fecha_envio)
            VALUES (NULL, 1, 'usuario', 29, 'profesional', 'user_to_pro', 'Mensaje de prueba desde test_mensaje.php', 0, 0, NOW())";

    if (!$conn->query($sql)) {
        throw new Exception("Error SQL: " . $conn->error);
    }

    echo "✅ Insert realizado con éxito. ID: " . $conn->insert_id;

} catch (Throwable $e) {
    echo "❌ Falló: " . $e->getMessage();
}
