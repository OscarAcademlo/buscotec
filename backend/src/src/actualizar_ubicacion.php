<?php
// backend/actualizar_ubicacion.php
// Guarda o actualiza la ubicación del profesional al iniciar sesión

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/session_boot.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol'])) {
  echo json_encode(['ok' => false, 'error' => 'No hay sesión activa.']);
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$rol = $_SESSION['rol'];
$lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
$lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;

if (!$lat || !$lng) {
  echo json_encode(['ok' => false, 'error' => 'Coordenadas no válidas']);
  exit;
}

try {
  $stmt = $conn->prepare("
    INSERT INTO ubicaciones_usuarios (user_id, rol, lat, lng, updated_at)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), updated_at = NOW()
  ");
  $stmt->bind_param('isdd', $user_id, $rol, $lat, $lng);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  error_log('[UBICACION] ' . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => 'Error al guardar ubicación']);
}
?>
