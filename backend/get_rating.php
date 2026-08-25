<?php
// backend/get_rating.php — devuelve promedio y total de calificaciones
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Validar ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'ID inválido']);
  exit;
}

// Consultar promedio y cantidad de calificaciones
$sql = "SELECT 
          AVG(puntuacion) AS promedio, 
          COUNT(*) AS total 
        FROM calificaciones 
        WHERE receptor_profesional_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
  'ok' => true,
  'promedio' => (float)($res['promedio'] ?? 0),
  'total' => (int)($res['total'] ?? 0)
], JSON_UNESCAPED_UNICODE);
