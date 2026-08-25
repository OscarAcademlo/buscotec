<?php
// backend/listar_profesionales.php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT id, nombre, apellido, email, webpushr_id 
            FROM profesionales 
            ORDER BY id ASC";
    $res = $conn->query($sql);

    $profes = [];
    while ($row = $res->fetch_assoc()) {
        $profes[] = $row;
    }

    echo json_encode([
        "ok" => true,
        "profesionales" => $profes
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
