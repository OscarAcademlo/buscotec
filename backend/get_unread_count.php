<?php
/**
 * backend/get_unread_count.php
 * Retorna el conteo de mensajes no leídos para el usuario actual.
 * Versión ultra-robusta sin strict_types.
 */
error_reporting(0); // Prevenir que warnings rompan el JSON
ini_set('display_errors', '0');

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

if (file_exists(__DIR__ . '/cors_helper.php')) {
    require_once __DIR__ . '/cors_helper.php';
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-Id, X-User-Role, X-User-Email');
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Limpiar cualquier salida previa accidental
if (ob_get_length())
  ob_clean();
header('Content-Type: application/json; charset=utf-8');

$userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Fallback para parámetros GET o Headers (App móvil)
if ($userId <= 0) {
  if (isset($_GET['user_id']))
    $userId = (int) $_GET['user_id'];
  elseif (isset($_SERVER['HTTP_X_USER_ID']))
    $userId = (int) $_SERVER['HTTP_X_USER_ID'];
}
if (empty($email)) {
  if (isset($_GET['email']))
    $email = $_GET['email'];
  elseif (isset($_SERVER['HTTP_X_USER_EMAIL']))
    $email = $_SERVER['HTTP_X_USER_EMAIL'];
}
if (empty($role)) {
  if (isset($_GET['role']))
    $role = $_GET['role'];
  elseif (isset($_SERVER['HTTP_X_USER_ROLE']))
    $role = $_SERVER['HTTP_X_USER_ROLE'];
}

if ($userId <= 0 && empty($email)) {
  echo json_encode(['ok' => false, 'unread' => 0, 'no_leidos' => 0, 'error' => 'No identificado']);
  exit;
}

// Conexión fallida
if (!isset($conn) || !($conn instanceof mysqli)) {
  echo json_encode(['ok' => false, 'unread' => 0, 'no_leidos' => 0, 'error' => 'Sin conexión DB']);
  exit;
}

try {
  // 1. Si no tenemos email, buscarlo por ID
  if (empty($email) && $userId > 0) {
    $tabla = ($role === 'profesional') ? 'profesionales' : 'usuarios';
    $stmt = $conn->prepare("SELECT email FROM $tabla WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $email = $row['email'];
      }
      $stmt->close();
    }
  }

  // 2. Obtener IDs de todos los roles asociados al email
  $role_ids = isset($_SESSION['role_ids']) && is_array($_SESSION['role_ids']) ? $_SESSION['role_ids'] : [];
  if (empty($role_ids) && !empty($email)) {
    // Buscar en usuarios
    $stmtU = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    if ($stmtU) {
      $stmtU->bind_param('s', $email);
      $stmtU->execute();
      $msgResU = $stmtU->get_result();
      if ($rowU = $msgResU->fetch_assoc())
        $role_ids['usuario'] = (int) $rowU['id'];
      $stmtU->close();
    }
    // Buscar en profesionales
    $stmtP = $conn->prepare("SELECT id FROM profesionales WHERE email = ? LIMIT 1");
    if ($stmtP) {
      $stmtP->bind_param('s', $email);
      $stmtP->execute();
      $msgResP = $stmtP->get_result();
      if ($rowP = $msgResP->fetch_assoc())
        $role_ids['profesional'] = (int) $rowP['id'];
      $stmtP->close();
    }
  }

  // Fallback: usar el ID que ya tenemos si no encontramos nada más
  if (empty($role_ids) && $userId > 0) {
    $role_ids[$role ?: 'usuario'] = $userId;
  }

  $totalUnread = 0;

  // 3. Contar mensajes no leídos
  if (isset($role_ids['usuario']) && $role_ids['usuario'] > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM mensajes WHERE destinatario_tipo='usuario' AND destinatario_id=? AND leido=0");
    if ($stmt) {
      $stmt->bind_param('i', $role_ids['usuario']);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc())
        $totalUnread += (int) $row['c'];
      $stmt->close();
    }
  }
  if (isset($role_ids['profesional']) && $role_ids['profesional'] > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM mensajes WHERE destinatario_tipo='profesional' AND destinatario_id=? AND leido=0");
    if ($stmt) {
      $stmt->bind_param('i', $role_ids['profesional']);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc())
        $totalUnread += (int) $row['c'];
      $stmt->close();
    }
  }

  echo json_encode([
    'ok' => true,
    'unread' => $totalUnread,
    'no_leidos' => $totalUnread, // Compatibilidad
    'userId' => $userId,
    'role' => $role
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (ob_get_length())
    ob_clean();
  echo json_encode(['ok' => false, 'unread' => 0, 'no_leidos' => 0, 'error' => $e->getMessage()]);
}
exit;
