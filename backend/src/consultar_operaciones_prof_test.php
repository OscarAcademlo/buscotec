<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_operaciones_prof_test.log');

// 🔹 ID fijo de Oscar para pruebas
$profId = 33;

$sql = "
SELECT 
  c.id AS id_caso,
  c.estado,
  c.cargo_usd,
  c.accepted_at,
  c.created_at,
  c.solicitante_id,
  c.solicitante_tipo,
  c.receptor_id,

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
  AND c.estado = 'aceptado'
ORDER BY c.accepted_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $profId);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$total = 0;
$i = 1;

while ($r = $res->fetch_assoc()) {
  $costo = (float)($r['cargo_usd'] ?: 1.00);
  $total += $costo;
  $items[] = [
    '#'        => $i++,
    'cliente'  => $r['cliente'],
    'whatsapp' => $r['whatsapp_cliente'],
    'fecha'    => $r['accepted_at'] ?: $r['created_at'],
    'costo'    => '$'.number_format($costo, 2)
  ];
}

$stmt->close();

echo json_encode([
  'ok' => true,
  'profesional_id' => $profId,
  'items' => $items,
  'total' => '$'.number_format($total, 2)
], JSON_UNESCAPED_UNICODE);
