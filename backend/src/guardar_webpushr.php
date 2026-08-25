<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);
$webpushr_id = trim($data['webpushr_id'] ?? '');

if (!$id || !$webpushr_id) {
    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
    exit;
}

$sql = "UPDATE profesionales SET webpushr_id=? WHERE id=?";
$stmt = $conn->prepare($sql);
$ok = $stmt->execute([$webpushr_id, $id]);

echo json_encode([
    'ok' => $ok,
    'saved' => $webpushr_id,
    'id' => $id
]);
?>
