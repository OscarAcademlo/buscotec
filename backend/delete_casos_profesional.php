<?php
// backend/delete_casos_profesional.php — BuscoTec Admin 2025
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

$profesional_id = intval($_POST['profesional_id'] ?? 0);
if ($profesional_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM casos WHERE profesional_id = ?");
    $stmt->bind_param('i', $profesional_id);
    $stmt->execute();
    $borrados = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['ok' => true, 'deleted' => $borrados]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
