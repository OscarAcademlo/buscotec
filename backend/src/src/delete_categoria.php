<?php
// backend/delete_categoria.php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sin conexión a la base de datos']);
    exit;
}
$conn->set_charset('utf8mb4');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    // Verificar que exista
    $verif = $conn->prepare("SELECT id FROM categorias WHERE id = ?");
    $verif->bind_param('i', $id);
    $verif->execute();
    $res = $verif->get_result();
    if (!$res->fetch_assoc()) {
        echo json_encode(['ok' => false, 'error' => 'Categoría no encontrada']);
        exit;
    }
    $verif->close();

    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true, 'msg' => 'Categoría eliminada']);
} catch (Throwable $e) {
    error_log('[DELETE_CATEGORIA] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error al eliminar categoría']);
}
