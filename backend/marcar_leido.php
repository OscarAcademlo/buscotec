<?php
/**
 * backend/marcar_leido.php
 * Marca un mensaje o caso como leído.
 */
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . "/session_boot.php";
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

if (ob_get_length())
  ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
  echo json_encode(['ok' => false, 'error' => 'Sin conexión DB']);
  exit;
}

$mensaje_id = isset($_POST['mensaje_id']) ? (int) $_POST['mensaje_id'] : 0;
$caso_id = isset($_POST['caso_id']) ? (int) $_POST['caso_id'] : 0;

if ($mensaje_id <= 0 && $caso_id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'ID inválido']);
  exit;
}

/* --- Autenticación --- */
$userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';

// Fallback para app móvil
if ($userId <= 0) {
  if (!empty($_SERVER['HTTP_X_USER_ID'])) {
    $userId = (int) $_SERVER['HTTP_X_USER_ID'];
    $role = trim($_SERVER['HTTP_X_USER_ROLE'] ?? '');
  } elseif (isset($_POST['_uid'])) {
    $userId = (int) $_POST['_uid'];
    $role = trim($_POST['_role'] ?? '');
  }
}

try {
  // Marcar mensaje
  if ($mensaje_id > 0) {
    $stmt = $conn->prepare("UPDATE mensajes SET leido = 1 WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param('i', $mensaje_id);
      $stmt->execute();
      $stmt->close();
    }
  }

  // Marcar caso
  if ($caso_id > 0) {
    $stmt = $conn->prepare("UPDATE mensajes SET leido = 1 WHERE caso_id = ? AND tipo = 'sistema'");
    if ($stmt) {
      $stmt->bind_param('i', $caso_id);
      $stmt->execute();
      $stmt->close();
    }
  }

  // Devolver conteo actualizado
  // (Buscamos el email si no lo tenemos para contar bien)
  if (empty($email) && $userId > 0) {
    $tabla = ($role === 'profesional') ? 'profesionales' : 'usuarios';
    $stmt = $conn->prepare("SELECT email FROM $tabla WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc())
        $email = $row['email'];
      $stmt->close();
    }
  }

  $totalUnread = 0;
  if (!empty($email)) {
    // Conteo por email (ambos roles)
    $stmtU = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    if ($stmtU) {
      $stmtU->execute([$email]);
      $rU = $stmtU->get_result()->fetch_assoc();
      if ($rU) {
        $countU = $conn->query("SELECT COUNT(*) FROM mensajes WHERE destinatario_tipo='usuario' AND destinatario_id={$rU['id']} AND leido=0")->fetch_row()[0];
        $totalUnread += (int) $countU;
      }
      $stmtU->close();
    }
    $stmtP = $conn->prepare("SELECT id FROM profesionales WHERE email = ? LIMIT 1");
    if ($stmtP) {
      $stmtP->execute([$email]);
      $rP = $stmtP->get_result()->fetch_assoc();
      if ($rP) {
        $countP = $conn->query("SELECT COUNT(*) FROM mensajes WHERE destinatario_tipo='profesional' AND destinatario_id={$rP['id']} AND leido=0")->fetch_row()[0];
        $totalUnread += (int) $countP;
      }
      $stmtP->close();
    }
  } else if ($userId > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM mensajes WHERE destinatario_id=? AND leido=0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $totalUnread = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
  }

  echo json_encode([
    'ok' => true,
    'unread' => $totalUnread,
    'no_leidos' => $totalUnread
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
exit;
