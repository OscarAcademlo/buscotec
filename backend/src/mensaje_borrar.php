<?php
// backend/mensaje_borrar.php — versión sin sesión, 100% funcional
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/error_log_mensaje_borrar.log');

try {
    if (!isset($conn) && isset($conexion)) $conn = $conexion;
    if (!($conn instanceof mysqli)) throw new Exception('Sin conexión a la base de datos');
    $conn->set_charset('utf8mb4');

    // ✅ Obtener mensaje_id
    $mensaje_id = isset($_POST['mensaje_id']) ? (int)$_POST['mensaje_id'] : 0;
    if ($mensaje_id <= 0) throw new Exception('ID de mensaje inválido');

    // ✅ Eliminar adjuntos (si existen)
    $selAdj = $conn->prepare("SELECT ruta FROM mensaje_adjuntos WHERE mensaje_id=?");
    $selAdj->bind_param("i", $mensaje_id);
    $selAdj->execute();
    $resAdj = $selAdj->get_result();
    while ($adj = $resAdj->fetch_assoc()) {
        $ruta = basename(trim($adj['ruta']));
        $path = __DIR__ . '/../uploads_mensajes/' . $ruta;
        if (file_exists($path)) @unlink($path);
    }
    $selAdj->close();

    // ✅ Eliminar registros
    $conn->query("DELETE FROM mensaje_adjuntos WHERE mensaje_id = $mensaje_id");
    $conn->query("DELETE FROM mensajes WHERE id = $mensaje_id");

    if ($conn->errno) throw new Exception('Error SQL: ' . $conn->error);

    echo json_encode(['ok' => true, 'mensaje' => 'Mensaje eliminado correctamente'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
