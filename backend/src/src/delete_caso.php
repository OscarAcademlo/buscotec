<?php
// backend/delete_caso.php — eliminar caso individual
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok'=>false,'error'=>'Sin conexión a la base de datos']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM casos WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    echo json_encode(['ok'=>$ok]);
} catch (Throwable $e) {
    error_log("[delete_caso] ".$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'Error al eliminar']);
}
