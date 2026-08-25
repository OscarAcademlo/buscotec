<?php
// backend/get_calificaciones_profesional.php — versión extendida sin romper compatibilidad
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$id  = intval($_GET['id'] ?? 0);
$all = intval($_GET['all'] ?? 0); // 👈 nuevo parámetro opcional

if ($id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'ID inválido']);
  exit;
}

try {
  // Base SQL: incluye nombre del usuario
  $sql = "SELECT 
            c.puntuacion, 
            c.comentario, 
            DATE_FORMAT(c.fecha_creacion, '%Y-%m-%d %H:%i') AS fecha,
            u.nombre,
            u.apellido
          FROM calificaciones c
          LEFT JOIN usuarios u ON u.id = c.emisor_id
          WHERE c.receptor_profesional_id = ?
          ORDER BY c.fecha_creacion DESC";

  // 👇 Si no se pasa ?all=1, limita a 3 resultados
  if (!$all) $sql .= " LIMIT 3";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();

  $items = [];
  while ($row = $res->fetch_assoc()) $items[] = $row;

  echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
