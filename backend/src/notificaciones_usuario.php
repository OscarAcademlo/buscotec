<?php
// backend/notificaciones_usuario.php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$userId = intval($_GET['user_id'] ?? 0);
if ($userId <= 0) {
  echo json_encode(['ok' => false, 'error' => 'Usuario inválido']);
  exit;
}

try {
  $sql = "SELECT c.id AS caso_id, p.nombre AS profesional, p.whatsapp, c.updated_at
          FROM casos c
          JOIN profesionales p ON c.profesional_id = p.id
          WHERE c.user_id = ? AND c.estado = 'aceptado' AND c.notificado = 0
          ORDER BY c.updated_at DESC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $items = [];
  while ($row = $res->fetch_assoc()) $items[] = $row;
  $stmt->close();

  echo json_encode(['ok' => true, 'items' => $items]);
  
  // 🔄 Marcar como notificado
  if ($items) {
    $ids = array_column($items, 'caso_id');
    $in = implode(',', array_map('intval', $ids));
    $conn->query("UPDATE casos SET notificado = 1 WHERE id IN ($in)");
  }

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
