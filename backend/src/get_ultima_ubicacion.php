<?php
// backend/guardar_ubicacion.php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . "/session_boot.php";

function respond($ok, $extra = []) {
  echo json_encode(array_merge(['success' => $ok], $extra));
  exit;
}

try {
  if (!isset($_SESSION['id'], $_SESSION['role'])) {
    respond(false, ['message' => 'No hay sesión activa']);
  }

  $user_id = (int)$_SESSION['id'];
  $rol = strtolower($_SESSION['role']);
  $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
  $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

  if (!$lat || !$lng) respond(false, ['message' => 'Coordenadas inválidas']);

  $db = $conn ?? null;
  if (!$db || !($db instanceof mysqli)) respond(false, ['message' => 'Conexión no disponible']);

  // Crear tabla si no existe
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

  // Insertar o actualizar
  $sql = "
    INSERT INTO ubicaciones_usuarios (user_id, rol, lat, lng)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), updated_at = CURRENT_TIMESTAMP
  ";
  $st = $db->prepare($sql);
  $st->bind_param('isdd', $user_id, $rol, $lat, $lng);
  $ok = $st->execute();

  respond($ok, ['lat' => $lat, 'lng' => $lng]);

} catch (Throwable $e) {
  error_log('guardar_ubicacion error: ' . $e->getMessage());
  respond(false, ['message' => 'Error interno']);
}
