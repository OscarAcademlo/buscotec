<?php
// ============================================================
// backend/consultar_operaciones_prof.php — Estado de Cuenta Profesional (2026-01-24)
// ============================================================
declare(strict_types=1);

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/session_boot.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug_operaciones_prof.log');

function out(array $a)
{
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}
function fail(string $msg)
{
  error_log('[consultar_operaciones_prof] ' . $msg);
  out(['ok' => false, 'error' => $msg]);
}

if (!($conn instanceof mysqli))
  fail('Sin conexión a DB');
$conn->set_charset('utf8mb4');

function normalizarCargo(float $cargoArs, float $cargoUsd, float $valorGlobal): float
{
  if ($cargoArs > 0) return $cargoArs;
  if ($cargoUsd > 0) return $cargoUsd;
  return $valorGlobal;
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = $res && $res->num_rows > 0;
  $stmt->close();
  return $ok;
}

// --- Obtener ID profesional de forma dinámica ---
$profId = null;

// A) Headers de la App (X-User-Id)
$hId = $_SERVER['HTTP_X_USER_ID'] ?? '';
$hRole = $_SERVER['HTTP_X_USER_ROLE'] ?? '';
if ($hId && $hRole === 'profesional') {
    $profId = (int)$hId;
}

// 1) Intentar desde parámetro GET/POST
if (!$profId && !empty($_REQUEST['profesional_id'])) {
    $profId = (int) $_REQUEST['profesional_id'];
}

// 2) Si no viene, intentar desde sesión (múltiples formatos)
if (!$profId) {
  // Formato nuevo: role_ids['profesional']
  if (!empty($_SESSION['role_ids']['profesional'])) {
    $profId = (int) $_SESSION['role_ids']['profesional'];
  }
  // Formato alternativo: id directo si el role es profesional
  elseif (!empty($_SESSION['id']) && !empty($_SESSION['role']) && $_SESSION['role'] === 'profesional') {
    $profId = (int) $_SESSION['id'];
  }
  // Formato legacy: profesional_id directo
  elseif (!empty($_SESSION['profesional_id'])) {
    $profId = (int) $_SESSION['profesional_id'];
  }
}

// 3) Si aún no hay, buscar por email en sesión
if (!$profId && !empty($_SESSION['email'])) {
  $stmt = $conn->prepare("SELECT id FROM profesionales WHERE email = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('s', $_SESSION['email']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $profId = (int) $row['id'];
    }
    $stmt->close();
  }
}

// 4) Log de debug para ver qué hay en la sesión
error_log('[consultar_operaciones_prof] SESSION: ' . json_encode($_SESSION));
error_log('[consultar_operaciones_prof] profId detectado: ' . ($profId ?: 'NULL'));

if (!$profId)
  fail('No se pudo identificar al profesional. Iniciá sesión como profesional.');

// --- Valor del caso (ARS) desde ajustes ---
$valorCaso = 1400.0;
$resV = $conn->query("SELECT valor FROM ajustes WHERE clave IN ('valor_caso', 'valor_caso_ars', 'valor_caso_usd') ORDER BY FIELD(clave, 'valor_caso', 'valor_caso_ars', 'valor_caso_usd') ASC LIMIT 1");
if ($resV && ($row = $resV->fetch_assoc())) {
  $tmp = (float) $row['valor'];
  if ($tmp > 0) $valorCaso = $tmp;
}

// --- Consulta principal (doble rol) ---
$sql = "
SELECT 
  c.id AS id_caso,
  c.estado,
  c.cargo_usd,
  c.cargo_ars,
  c.accepted_at,
  c.created_at,
  c.solicitante_id,
  c.solicitante_tipo,
  c.receptor_id,
  COALESCE(c.pagado, 0) AS pagado,
  " . (hasColumn($conn, 'casos', 'pagado_at') ? "c.pagado_at" : "NULL AS pagado_at") . ",

  CASE 
    WHEN c.solicitante_tipo = 'usuario' THEN CONCAT(IFNULL(u.nombre,''), ' ', IFNULL(u.apellido,''))
    WHEN c.solicitante_tipo = 'profesional' THEN p2.nombre
    ELSE '(sin nombre)'
  END AS cliente,

  CASE 
    WHEN c.solicitante_tipo = 'usuario' THEN u.whatsapp
    WHEN c.solicitante_tipo = 'profesional' THEN p2.whatsapp
    ELSE '-'
  END AS whatsapp_cliente

FROM casos c
LEFT JOIN usuarios u ON u.id = c.solicitante_id
LEFT JOIN profesionales p2 ON p2.id = c.solicitante_id
WHERE c.receptor_id = ?
  AND LOWER(c.estado) = 'aceptado'
ORDER BY c.accepted_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt)
  fail('Error SQL: ' . $conn->error);

$stmt->bind_param('i', $profId);
if (!$stmt->execute())
  fail('Error ejecutando SQL: ' . $stmt->error);
$res = $stmt->get_result();

$items = [];
$total = 0.0;
$i = 1;

while ($r = $res->fetch_assoc()) {
  $nombre = trim((string) ($r['cliente'] ?? '(sin nombre)'));
  $wa = trim((string) ($r['whatsapp_cliente'] ?? '-'));
  $fecha = $r['accepted_at'] ?: $r['created_at'];

  $cargoUsd = (float) ($r['cargo_usd'] ?? 0);
  $cargoArsTable = (float) ($r['cargo_ars'] ?? 0);
  $cargoArs = normalizarCargo($cargoArsTable, $cargoUsd, $valorCaso);
  $isPagado = (int)($r['pagado'] ?? 0);

  if (!$isPagado) {
    $total += $cargoArs;
  }

  $items[] = [
    '#' => $i++,
    'id_caso' => (int) $r['id_caso'],
    'cliente' => $nombre,
    'whatsapp' => $wa,
    'fecha' => $fecha,
    'cargo_ars' => $cargoArs,
    'costo' => '$' . number_format($cargoArs, 2, ',', '.'),
    'pagado' => $isPagado,
    'pagado_at' => $r['pagado_at']
  ];
}
$stmt->close();

out([
  'ok' => true,
  'profesional_id' => $profId,
  'valor_caso_ars' => $valorCaso,
  'items' => $items,
  'total' => '$' . number_format($total, 2, ',', '.')
]);
