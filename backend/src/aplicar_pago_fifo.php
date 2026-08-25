<?php
declare(strict_types=1);

require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']);
  exit;
}

$profesional_id = (int)($_POST['profesional_id'] ?? 0);
$monto_pago     = (float)($_POST['monto'] ?? 0);

if ($profesional_id <= 0 || $monto_pago <= 0) {
  echo json_encode(['ok'=>false,'error'=>'Datos inválidos']);
  exit;
}

$conn->set_charset('utf8mb4');

try {
  $conn->begin_transaction();

  // 1️⃣ Registrar el pago
  $stmt = $conn->prepare("
    INSERT INTO pagos_profesionales (profesional_id, monto, fecha_pago, registrado_por)
    VALUES (?, ?, NOW(), 'admin')
  ");
  $stmt->bind_param('id', $profesional_id, $monto_pago);
  $stmt->execute();
  $pago_id = $stmt->insert_id;
  $stmt->close();

  $restante = $monto_pago;

  // 2️⃣ Traer cargos pendientes/parciales (FIFO)
  $q = $conn->prepare("
    SELECT 
      cc.id AS cargo_id,
      cc.caso_id,
      cc.monto,
      IFNULL(SUM(pa.monto_aplicado),0) AS ya_pagado
    FROM casos_cargos cc
    LEFT JOIN pagos_aplicaciones pa ON pa.cargo_id = cc.id
    WHERE cc.profesional_id = ?
    GROUP BY cc.id
    HAVING ya_pagado < cc.monto
    ORDER BY cc.creado_en ASC
  ");
  $q->bind_param('i', $profesional_id);
  $q->execute();
  $res = $q->get_result();

  // 3️⃣ Repartir el pago
  while ($row = $res->fetch_assoc()) {
    if ($restante <= 0) break;

    $debe      = (float)$row['monto'];
    $ya_pagado = (float)$row['ya_pagado'];
    $pendiente = $debe - $ya_pagado;

    $aplicar = min($pendiente, $restante);

    $ins = $conn->prepare("
      INSERT INTO pagos_aplicaciones (pago_id, caso_id, cargo_id, monto_aplicado)
      VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param(
      'iiid',
      $pago_id,
      $row['caso_id'],
      $row['cargo_id'],
      $aplicar
    );
    $ins->execute();
    $ins->close();

    $restante -= $aplicar;
  }

  $conn->commit();

  echo json_encode([
    'ok'           => true,
    'pago_id'      => $pago_id,
    'monto'        => $monto_pago,
    'remanente'    => $restante
  ]);

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
