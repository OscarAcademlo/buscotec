<?php
declare(strict_types=1);

/* ============================================================
   backend/admin_imputar_pago_fifo.php — FIFO pagos (ARS)
   - Cada caso vale "valor_caso_ars" (ajustes) o fallback 1400.
   - Imputa pagos FIFO (más viejo primero) marcando casos como pagado.
   - Ignora cargo_usd para evitar el bug de $1 por caso.
   ============================================================ */

require_once __DIR__ . '/_api_boot.php'; // tu boot unificado (sesión/headers base)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . '/conexion.php';

function jexit(array $a, int $code = 200): void
{
  http_response_code($code);
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}

function getRequestData(): array
{
  // 1) POST normal
  if (!empty($_POST))
    return $_POST;

  // 2) JSON
  $raw = file_get_contents('php://input');
  if (!$raw)
    return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function n($x): float
{
  $v = (float) $x;
  return is_finite($v) ? $v : 0.0;
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $stmt = $conn->prepare($sql);
  if (!$stmt)
    return false;
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = $res && $res->num_rows > 0;
  $stmt->close();
  return $ok;
}

function tableExists(mysqli $conn, string $table): bool
{
  $stmt = $conn->prepare("SHOW TABLES LIKE ?");
  if (!$stmt)
    return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = $res && $res->num_rows > 0;
  $stmt->close();
  return $ok;
}

/* ============================================================
   AUTH ADMIN (igual criterio que tu front)
   ============================================================ */
$role = (string) ($_SESSION['role'] ?? '');
$email = strtolower((string) ($_SESSION['email'] ?? ''));

$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];

if ($role !== 'admin' && !in_array($email, $ADMIN_ALLOWLIST, true)) {
  jexit(['ok' => false, 'error' => 'No autorizado'], 401);
}

if (!($conn instanceof mysqli)) {
  jexit(['ok' => false, 'error' => 'DB fail'], 500);
}
$conn->set_charset('utf8mb4');

/* ============================================================
   INPUT
   Acepta:
   - profesional_id
   - monto_ars o monto
   - nota (opcional)
   ============================================================ */
$in = getRequestData();

$profesionalId = (int) ($in['profesional_id'] ?? 0);
$monto = n($in['monto_ars'] ?? ($in['monto'] ?? 0));
$nota = trim((string) ($in['nota'] ?? ''));

if ($profesionalId <= 0)
  jexit(['ok' => false, 'error' => 'profesional_id inválido'], 400);
if ($monto <= 0)
  jexit(['ok' => false, 'error' => 'monto inválido'], 400);

/* ============================================================
   Columnas requeridas: casos.pagado (+ pagado_at opcional)
   ============================================================ */
$hasPagado = hasColumn($conn, 'casos', 'pagado');
$hasPagadoAt = hasColumn($conn, 'casos', 'pagado_at');

if (!$hasPagado) {
  jexit([
    'ok' => false,
    'error' => "Falta columna casos.pagado. Ejecutá:
ALTER TABLE casos ADD COLUMN pagado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE casos ADD COLUMN pagado_at DATETIME NULL;"
  ], 500);
}

/* ============================================================
   Valor por caso (ARS) — fuente única de verdad
   ============================================================ */
$valorCaso = 0.0;

// 1) clave principal: valor_caso (la misma que usa el admin.html)
$stmt = $conn->prepare("SELECT valor FROM ajustes WHERE clave='valor_caso' LIMIT 1");
if ($stmt) {
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && ($row = $res->fetch_assoc()))
    $valorCaso = n($row['valor']);
  $stmt->close();
}

// 2) fallback: valor_caso_ars (compatibilidad)
if ($valorCaso <= 0) {
  $stmt = $conn->prepare("SELECT valor FROM ajustes WHERE clave='valor_caso_ars' LIMIT 1");
  if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc()))
      $valorCaso = n($row['valor']);
    $stmt->close();
  }
}

// 3) sanity check: si te devuelve 1 o 30, lo corregimos a 1400 para BuscoTec ARS
// (esto evita EXACTAMENTE el bug que estás viendo)
if ($valorCaso < 100) {
  $valorCaso = 1400.0;
}

if ($valorCaso <= 0)
  $valorCaso = 1400.0;

/* ============================================================
   Deuda por COUNT (no por cargo_usd)
   ============================================================ */
$sqlCount = "
  SELECT COUNT(*) AS pendientes
  FROM casos c
  WHERE c.receptor_id = ?
    AND LOWER(c.estado) = 'aceptado'
    AND COALESCE(c.pagado,0) = 0
";
$stCount = $conn->prepare($sqlCount);
if (!$stCount)
  jexit(['ok' => false, 'error' => 'Error prepare count: ' . $conn->error], 500);
$stCount->bind_param('i', $profesionalId);
$stCount->execute();
$rCount = $stCount->get_result();
$pendientesAntes = 0;
if ($rCount && ($row = $rCount->fetch_assoc()))
  $pendientesAntes = (int) $row['pendientes'];
$stCount->close();

if ($pendientesAntes <= 0) {
  jexit(['ok' => false, 'error' => 'Este profesional no tiene deuda pendiente.'], 400);
}

$deudaAntes = $pendientesAntes * $valorCaso;

if ($monto > $deudaAntes + 0.00001) {
  jexit([
    'ok' => false,
    'error' => 'El monto supera la deuda pendiente.',
    'valor_caso' => $valorCaso,
    'pendientes' => $pendientesAntes,
    'deuda_antes' => $deudaAntes
  ], 400);
}

// No pago parcial: debe cubrir casos completos
$casesToPay = (int) floor($monto / $valorCaso);
$resto = $monto - ($casesToPay * $valorCaso);
if ($casesToPay <= 0 || abs($resto) > 0.00001) {
  jexit([
    'ok' => false,
    'error' => 'El monto debe ser múltiplo del valor por caso (sin pagos parciales).',
    'valor_caso' => $valorCaso,
    'monto' => $monto
  ], 400);
}

/* ============================================================
   Transacción FIFO
   ============================================================ */
$conn->begin_transaction();

try {
  // (opcional) registrar pago si existe la tabla
  $pagoId = null;
  if (tableExists($conn, 'pagos_profesionales')) {
    $sqlIns = "INSERT INTO pagos_profesionales (profesional_id, monto, nota, created_at)
               VALUES (?, ?, ?, NOW())";
    $stIns = $conn->prepare($sqlIns);
    if (!$stIns)
      throw new Exception('Prepare insert pago: ' . $conn->error);
    $stIns->bind_param('ids', $profesionalId, $monto, $nota);
    $stIns->execute();
    $pagoId = (int) $stIns->insert_id;
    $stIns->close();
  }

  // Seleccionar IDs FIFO con lock
  $sqlIds = "
    SELECT c.id
    FROM casos c
    WHERE c.receptor_id = ?
      AND LOWER(c.estado) = 'aceptado'
      AND COALESCE(c.pagado,0) = 0
    ORDER BY c.accepted_at ASC, c.id ASC
    LIMIT ?
    FOR UPDATE
  ";
  $stIds = $conn->prepare($sqlIds);
  if (!$stIds)
    throw new Exception('Prepare select ids: ' . $conn->error);
  $stIds->bind_param('ii', $profesionalId, $casesToPay);
  $stIds->execute();
  $resIds = $stIds->get_result();

  $ids = [];
  while ($row = $resIds->fetch_assoc())
    $ids[] = (int) $row['id'];
  $stIds->close();

  if (count($ids) !== $casesToPay) {
    throw new Exception('No hay suficientes casos pendientes para imputar ese monto.');
  }

  // Update masivo
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $sqlUpd = $hasPagadoAt
    ? "UPDATE casos SET pagado=1, pagado_at=NOW() WHERE COALESCE(pagado,0)=0 AND id IN ($placeholders)"
    : "UPDATE casos SET pagado=1 WHERE COALESCE(pagado,0)=0 AND id IN ($placeholders)";

  $stUpd = $conn->prepare($sqlUpd);
  if (!$stUpd)
    throw new Exception('Prepare update: ' . $conn->error);

  // bind dinámico (mysqli requiere referencias; ...$ids puede fallar)
  $types = str_repeat('i', count($ids));
  $params = array_merge([$types], $ids);

  // convertir a referencias
  $refs = [];
  foreach ($params as $k => $v) {
    $refs[$k] = &$params[$k];
  }

  if (!call_user_func_array([$stUpd, 'bind_param'], $refs)) {
    throw new Exception('bind_param dinámico falló');
  }

  $stUpd->execute();
  $marcados = $stUpd->affected_rows;
  $stUpd->close();

  // Recalcular pendientes
  $stCount2 = $conn->prepare($sqlCount);
  if (!$stCount2)
    throw new Exception('Prepare count2: ' . $conn->error);
  $stCount2->bind_param('i', $profesionalId);
  $stCount2->execute();
  $r2 = $stCount2->get_result();
  $pendientesDespues = 0;
  if ($r2 && ($row = $r2->fetch_assoc()))
    $pendientesDespues = (int) $row['pendientes'];
  $stCount2->close();

  $deudaDespues = $pendientesDespues * $valorCaso;


  $conn->commit();

  jexit([
    'ok' => true,
    'pago_id' => $pagoId,
    'valor_caso' => $valorCaso,
    'cases_to_pay' => $casesToPay,
    'marcados' => $marcados,
    'ids_pagados' => $ids,
    'monto_ingresado' => $monto,
    'monto_aplicado' => $casesToPay * $valorCaso,
    'deuda_antes' => $deudaAntes,
    'deuda_despues' => $deudaDespues,
    'pendientes_antes' => $pendientesAntes,
    'pendientes_despues' => $pendientesDespues
  ]);

} catch (Throwable $e) {
  $conn->rollback();
  jexit(['ok' => false, 'error' => 'Fallo imputando pago FIFO: ' . $e->getMessage()], 500);
}
