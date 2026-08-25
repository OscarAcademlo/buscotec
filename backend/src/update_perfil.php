<?php
// ============================================================
// backend/get_perfil.php — versión unificada (2025)
// ============================================================
declare(strict_types=1);
ob_start();

// 🔒 Sesión unificada (igual que update_perfil.php)
session_name('BUSCOTECSESSID');
$path = __DIR__ . '/../tmp_sessions';
if (!file_exists($path)) mkdir($path, 0777, true);
session_save_path($path);

session_set_cookie_params([
  'lifetime' => 60 * 60 * 24 * 7,
  'path'     => '/',
  'secure'   => true,
  'httponly' => true,
  'samesite' => 'None'
]);
require_once __DIR__ . "/session_boot.php";

ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://buscotec.com.ar');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/conexion.php';

function out(array $r): void {
  if (ob_get_length()) ob_clean();
  echo json_encode($r, JSON_UNESCAPED_UNICODE);
  exit;
}

// ============================================================
// 🟨 Validar sesión
// ============================================================
if (!isset($_SESSION['roles'], $_SESSION['role_ids'])) {
  out(['ok' => false, 'error' => 'Sesión no activa.']);
}

$roles   = $_SESSION['roles'];
$roleIds = $_SESSION['role_ids'];

if (!($conn instanceof mysqli)) {
  out(['ok' => false, 'error' => 'Sin conexión a la base de datos.']);
}

$data = ['usuario' => null, 'profesional' => null];

// ============================================================
// 🟦 Obtener datos de USUARIO
// ============================================================
if (in_array('usuario', $roles) && isset($roleIds['usuario'])) {
  $uid = (int)$roleIds['usuario'];
  $stmt = $conn->prepare("
    SELECT id, nombre, apellido, email, whatsapp, domicilio, casa AS casa
    FROM usuarios WHERE id=? LIMIT 1
  ");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $data['usuario'] = $res->fetch_assoc();
  $stmt->close();
}

// ============================================================
// 🟩 Obtener datos de PROFESIONAL
// ============================================================
if (in_array('profesional', $roles) && isset($roleIds['profesional'])) {
  $pid = (int)$roleIds['profesional'];

  // Datos básicos
  $stmt = $conn->prepare("
    SELECT id, nombre, apellido, email, whatsapp, direccion, experiencia, descripcion
    FROM profesionales WHERE id=? LIMIT 1
  ");
  $stmt->bind_param('i', $pid);
  $stmt->execute();
  $res = $stmt->get_result();
  $data['profesional'] = $res->fetch_assoc();
  $stmt->close();

  // Categorías del profesional
  $data['profesional']['categorias'] = [];
  $sqlCat = "
    SELECT c.id, c.nombre
    FROM profesional_categorias pc
    INNER JOIN categorias c ON c.id = pc.categoria_id
    WHERE pc.profesional_id = $pid
  ";
  $res = $conn->query($sqlCat);
  while ($row = $res->fetch_assoc()) {
    $data['profesional']['categorias'][] = $row;
  }
  $res->close();
}

// ============================================================
// ✅ Respuesta final
// ============================================================
out(['ok' => true, 'data' => $data]);
?>
