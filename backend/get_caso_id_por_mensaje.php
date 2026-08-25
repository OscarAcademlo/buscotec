<?php
// ✅ Devuelve el caso.id real sin importar si se recibe caso.id o mensaje.id
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    // Si es caso.id → lo devuelve
    // Si es mensaje.id → busca el caso correspondiente
    $sql = "
        SELECT id AS caso_id FROM casos WHERE id = ?
        UNION
        SELECT id AS caso_id FROM casos WHERE mensaje_id = ?
        ORDER BY caso_id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id, $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        echo json_encode(['ok' => true, 'caso_id' => (int)$row['caso_id']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Caso no encontrado']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
