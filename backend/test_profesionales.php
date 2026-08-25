<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
$conn->set_charset('utf8mb4');

$cat = intval($_GET['id'] ?? 0);
$out = ['ok' => true, 'cat_id' => $cat, 'data' => []];

$sql = "SELECT p.id, p.nombre, p.apellido, p.lat, p.lng, c.nombre AS categoria
        FROM profesionales p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.categoria_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $cat);
$stmt->execute();
$res = $stmt->get_result();

while($r = $res->fetch_assoc()) $out['data'][] = $r;

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
