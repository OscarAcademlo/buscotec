<?php
// backend/registrar_ubicacion.php
// Guarda o actualiza la última geolocalización de un usuario por rol.

ini_set('display_errors', 1); // Desactivar en producción final
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php'; // Debe definir $conn (mysqli)

function respond($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Método no permitido');
    }

    // Sanitizar/validar
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $rol     = isset($_POST['rol']) ? strtolower(trim($_POST['rol'])) : '';
    $lat     = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng     = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

    if ($user_id <= 0) respond(false, 'user_id inválido');
    if (!in_array($rol, ['usuario','profesional'], true)) respond(false, 'rol inválido');
    if (!is_numeric($lat) || !is_numeric($lng)) respond(false, 'Coordenadas inválidas');

    // Asegurar conexión válida
    $db = $conn ?? null;
    if (!$db || !($db instanceof mysqli)) {
        respond(false, 'Conexión no disponible');
    }

    // Crear tabla si no existe (seguridad extra en entornos nuevos)
    $db->query("
        CREATE TABLE IF NOT EXISTS ubicaciones_usuarios (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          rol ENUM('usuario','profesional') NOT NULL,
          lat DECIMAL(10,7) NOT NULL,
          lng DECIMAL(10,7) NOT NULL,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_user_rol (user_id, rol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // UPSERT por (user_id, rol)
    $sql = "
        INSERT INTO ubicaciones_usuarios (user_id, rol, lat, lng)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            lat = VALUES(lat),
            lng = VALUES(lng),
            updated_at = CURRENT_TIMESTAMP
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('isdd', $user_id, $rol, $lat, $lng);
    $stmt->execute();

    respond(true, 'Ubicación registrada', [
        'user_id' => $user_id,
        'rol' => $rol,
        'lat' => $lat,
        'lng' => $lng
    ]);

} catch (Throwable $e) {
    error_log('registrar_ubicacion error: ' . $e->getMessage());
    respond(false, 'Error interno');
}
