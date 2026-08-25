<?php
// ============================================================
// backend/get_pagos_profesional.php — versión estable 2025
// Muestra SOLO los casos del profesional logueado.
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// ---- LOG silencioso (Hostinger oculta errores en pantalla) ----
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_pagos.log');

function json_fail(string $msg) {
  error_log('[get_pagos_profesional] ' . $msg);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function json_out(array $data) {
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// 1) Conexión y sesión
if (!($conn instanceof mysqli)) json_fail('Sin conexión a la base de datos');
$idSesion = (int)($_SESSION['id'] ?? 0);
$role     = strtolower((string)($_SESSION['role'] ?? ''));

// Aceptamos tres variantes de sesión: role_ids, role=profesional, o id directo
$profId = 0;
if (!empty($_SESSION['role_ids']['profesional'])) {
  $profId = (int)$_SESSION['role_ids']['profesional'];
} elseif ($role === 'profesional' && $idSesion > 0) {
  $profId = $idSesion;
}
if ($profId <= 0) json_fail('No sos profesional o no estás logueado');

// 2) Valor por caso (no dependemos de ajustes.valor_caso si no existe)
$valorCaso = 1.00;
$hasAjustes = $conn->query("SHOW TABLES LIKE 'ajustes'");
if ($hasAjustes && $hasAjustes->num_rows > 0) {
  // Chequear si la columna existe en la tabla ajustes
  $col = $conn->query("
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ajustes'
      AND COLUMN_NAME = 'valor_caso'
    LIMIT 1
  ");
  if ($col && $col->num_rows > 0) {
    $q = @$conn->query("SELECT valor_caso FROM ajustes LIMIT 1");
    if ($q && ($r = $q->fetch_assoc()) && isset($r['valor_caso'])) {
      $valorCaso = (float)$r['valor_caso'];
    }
  }
}

// 3) Traer casos del profesional (receptor_id = profesional)
$sql = "
SELECT 
  c.id AS caso_id,
  c.estado,
  c.accepted_at,
  COALESCE(c.cargo_usd, ?) AS cargo_usd,
  u.nombre,
  u.apellido,
  u.whatsapp
FROM casos c
LEFT JOIN usuarios u ON u.id = c.solicitante_id
WHERE c.receptor_id = ?
  AND c.receptor_tipo = 'profesional'
ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('Error preparando SQL: '.$conn->error);
if (!$stmt->bind_param('di', $valorCaso, $profId)) json_fail('Error bind_param: '.$stmt->error);
if (!$stmt->execute()) json_fail('Error ejecutando SQL: '.$stmt->error);

$res = $stmt->get_result();
if ($res === false) json_fail('Error al obtener resultado: '.$stmt->error);

// 4) Armar respuesta
$casos = [];
$total = 0.00;

while ($row = $res->fetch_assoc()) {
  $costo = (float)($row['cargo_usd'] ?? $valorCaso);
  $nombre = trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''));
  if ($nombre === '') $nombre = 'Cliente sin nombre';
  $whatsapp = $row['whatsapp'] ?? '';

  $fecha = $row['accepted_at'] ? date('Y-m-d H:i', strtotime($row['accepted_at'])) : '-';

  $casos[] = [
    'caso_id'  => (int)$row['caso_id'],
    'nombre'   => $nombre,
    'whatsapp' => $whatsapp,
    'fecha'    => $fecha,
    'costo'    => number_format($costo, 2, '.', '')
  ];
  $total += $costo;
}
$stmt->close();

// 5) Salida JSON
json_out([
  'ok' => true,
  'profesional_id' => $profId,
  'casos' => $casos,
  'total' => number_format($total, 2, '.', '')
]);
