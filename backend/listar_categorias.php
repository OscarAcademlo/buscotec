<?php
// backend/listar_categorias.php
ini_set('display_errors', 0);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT id, nombre FROM categorias ORDER BY nombre ASC";
    $st = $conn->prepare($sql);
    $st->execute();
    $res = $st->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = ['id' => (int)$r['id'], 'nombre' => $r['nombre']];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db']);
}
