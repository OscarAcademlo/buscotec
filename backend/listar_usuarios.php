<?php
// backend/listar_usuarios.php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT id, nombre, apellido, email, webpushr_id 
            FROM usuarios 
            ORDER BY id ASC";
    $res = $conn->query($sql);

    $usuarios = [];
    while ($row = $res->fetch_assoc()) {
        $usuarios[] = $row;
    }

    echo json_encode([
        "ok" => true,
        "usuarios" => $usuarios
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
