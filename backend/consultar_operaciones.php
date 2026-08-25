<?php
// ============================================================
// backend/consultar_operaciones.php — ARS + soporte pagado (FIFO)
// ============================================================
declare(strict_types=1);

session_name('BUSCOTECSESSID');
$path = __DIR__ . '/../tmp_sessions';
if (!file_exists($path)) {
  @mkdir($path, 0777, true);
}
session_save_path($path);

session_set_cookie_params([
  'lifetime' => 60 * 60 * 24 * 7,
  'path' => '/',
  'domain' => '.buscotec.click',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'None'
]);
require_once __DIR__ . "/session_boot.php";

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug_operaciones.log');

function jexit(array $a): void
{
  while (ob_get_level()) {
    ob_end_clean();
  }
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!($conn instanceof mysqli)) {
  jexit(['ok' => false, 'error' => 'Sin conexión a base de datos']);
}
$conn->set_charset('utf8mb4');

function columnExists(mysqli $conn, string $table, string $column): bool
{
  $dbRes = $conn->query("SELECT DATABASE() AS db");
  $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
  $db = $dbRow['db'] ?? '';
  if ($db === '')
    return false;

  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st)
    return false;
  $st->bind_param('sss', $db, $table, $column);
  $st->execute();
  $r = $st->get_result();
  $ok = ($r && $r->num_rows > 0);
  $st->close();
  return $ok;
}

// ============================================================
// 1) Valor por caso (ARS) desde ajustes
//    - Preferimos 'valor_caso_ars'; si no existe, usamos 'valor_caso_usd'
// ============================================================
$valorCaso = 1400.0;

$sqlValor = "
  SELECT valor
  FROM ajustes
  WHERE clave IN ('valor_caso', 'valor_caso_ars', 'valor_caso_usd')
  ORDER BY FIELD(clave, 'valor_caso', 'valor_caso_ars', 'valor_caso_usd') ASC
  LIMIT 1
";
$resValor = $conn->query($sqlValor);
if ($resValor && ($row = $resValor->fetch_assoc())) {
  $v = (float) $row['valor'];
  if ($v > 0)
    $valorCaso = $v;
}

$hasPagado = columnExists($conn, 'casos', 'pagado');

// ============================================================
// 2) Items por profesional (contabilidad coherente)
//    total = (pend + pag) * valorCaso
//    pago  = pag * valorCaso            (simple y consistente)
//    saldo = pend * valorCaso
// ============================================================
$pagadoExpr = $hasPagado ? "IFNULL(c.pagado,0)" : "0";

$sql = "
SELECT
  p.id,
  p.nombre,
  p.apellido,
  p.foto_profesional,
  p.whatsapp,
  p.direccion,
  p.barrio,
  p.ciudad,
  p.provincia,
  p.estado_servicio,
  up.updated_at AS ultima_conexion,

  SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) AS cantidad,
  SUM(CASE WHEN c.id IS NOT NULL AND {$pagadoExpr}=0 THEN 1 ELSE 0 END) AS pendientes,
  SUM(CASE WHEN c.id IS NOT NULL AND {$pagadoExpr}=1 THEN 1 ELSE 0 END) AS pagados

FROM profesionales p
LEFT JOIN casos c
  ON c.receptor_id = p.id
  AND LOWER(c.estado) = 'aceptado'
LEFT JOIN ubicaciones_usuarios up 
  ON up.user_id = p.id AND up.rol = 'profesional'
WHERE p.email NOT LIKE '%bot%' 
  AND p.email NOT LIKE '%test%' 
  AND p.email NOT LIKE '%example%'
  AND p.verificado = 1
GROUP BY p.id
ORDER BY p.nombre ASC
";

$res = $conn->query($sql);
if (!$res) {
  jexit(['ok' => false, 'error' => 'Error SQL profesionales: ' . $conn->error]);
}

$items = [];
$totalGeneral = 0.0;

// Temporarily store items by ID to easily attach categories
$itemsById = [];

while ($r = $res->fetch_assoc()) {
  $cantidad = (int) ($r['cantidad'] ?? 0);
  $pendientes = (int) ($r['pendientes'] ?? 0);
  $pagados = (int) ($r['pagados'] ?? 0);

  $total = ($cantidad * $valorCaso);
  $pago = ($pagados * $valorCaso);
  $saldo = ($pendientes * $valorCaso);

  $item = [
    'id' => (int) $r['id'],
    'profesional' => trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
    'nombre' => trim((string)$r['nombre']),
    'apellido' => trim((string)$r['apellido']),
    'foto' => (string) ($r['foto_profesional'] ?? ''),
    'whatsapp' => (string) ($r['whatsapp'] ?? ''),
    'direccion' => (string) ($r['direccion'] ?? ''),
    'barrio' => (string) ($r['barrio'] ?? ''),
    'ciudad' => (string) ($r['ciudad'] ?? ''),
    'provincia' => (string) ($r['provincia'] ?? ''),
    'estado_servicio' => (int) ($r['estado_servicio'] ?? 0),
    'ultima_conexion' => (string) ($r['ultima_conexion'] ?? ''),
    'categorias' => '', // Will be filled later
    'cantidad' => $cantidad,
    'total' => number_format($total, 2, '.', ''),
    'pago' => number_format($pago, 2, '.', ''),
    'saldo' => number_format($saldo, 2, '.', ''),
    'pendientes' => $pendientes,
    'pagados' => $pagados
  ];

  $items[] = $item;
  $itemsById[$item['id']] = &$items[count($items) - 1];

  $totalGeneral += $saldo; // saldo pendiente global
}

// ============================================================
// 2.5) Fetch Categories separately
// ============================================================
// We fetch all categories map [profesional_id] => [cat1, cat2...]
$catSql = "
  SELECT cp.profesional_id, c.nombre
  FROM profesional_categorias cp
  JOIN categorias c ON c.id = cp.categoria_id
  ORDER BY c.nombre ASC
";
$catRes = $conn->query($catSql);
if ($catRes) {
  $catsByProf = [];
  while ($row = $catRes->fetch_assoc()) {
    $pid = (int) $row['profesional_id'];
    $catsByProf[$pid][] = $row['nombre'];
  }
  // Attach to items
  foreach ($catsByProf as $pid => $names) {
    if (isset($itemsById[$pid])) {
      $itemsById[$pid]['categorias'] = implode(', ', $names);
    }
  }
  // Also fetch legacy category if needed, but the new system handles it.
  // If we wanted to be super robust we could check p.categoria_id too, but let's trust the new table.
}

// ============================================================
// 3) Detalles por caso (para el modal)
//    Importante: devolvemos cargo_ars = valorCaso (ARS).
//    Si todavía no hay columna pagado, devolvemos pagado=0.
// ============================================================
$selectPagado = $hasPagado ? "IFNULL(c.pagado,0) AS pagado" : "0 AS pagado";

$sqlDetalles = "
SELECT
  c.receptor_id AS profesional_id,
  c.id AS caso_id,
  {$selectPagado},
  LOWER(c.estado) AS estado,
  DATE_FORMAT(c.accepted_at, '%Y-%m-%d') AS dia,
  DATE_FORMAT(c.accepted_at, '%H:%i:%s') AS hora,
  c.cargo_ars,

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
WHERE LOWER(c.estado) = 'aceptado'
ORDER BY c.receptor_id, c.accepted_at ASC, c.id ASC
";

$detRes = $conn->query($sqlDetalles);
if (!$detRes) {
  jexit(['ok' => false, 'error' => 'Error SQL detalles: ' . $conn->error]);
}

$detalles = [];
while ($d = $detRes->fetch_assoc()) {
  $pid = (int) $d['profesional_id'];

  $detalles[$pid][] = [
    'profesional_id' => $pid,
    'caso_id' => (int) $d['caso_id'],
    'pagado' => (int) ($d['pagado'] ?? 0),
    'cargo_ars' => (float) ($d['cargo_ars'] ?? $valorCaso), // <- ARS real del caso o fallback
    'estado' => (string) ($d['estado'] ?? 'aceptado'),
    'dia' => (string) ($d['dia'] ?? ''),
    'hora' => (string) ($d['hora'] ?? ''),
    'cliente' => trim((string) ($d['cliente'] ?? '(sin nombre)')),
    'whatsapp' => trim((string) ($d['whatsapp_cliente'] ?? '-')),
  ];
}

jexit([
  'ok' => true,
  'valor' => $valorCaso,
  'items' => $items,
  'total' => number_format($totalGeneral, 2, '.', ''), // total pendiente global
  'detalles' => $detalles
]);
