<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: text/plain; charset=utf-8');

// Forzar el ID de Oscar
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

while ($r = $res->fetch_assoc()) {
  echo $r['id_caso'] . " | " . $r['cliente'] . " | " . $r['whatsapp_cliente'] . " | " . $r['cargo_usd'] . PHP_EOL;
}
