<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok'=>false,'error'=>'Sin conexión']);
    exit;
}

$conn->set_charset('utf8mb4');

$res = $conn->query("SELECT valor FROM ajustes WHERE clave='valor_caso'");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode(['ok'=>true, 'valor'=>$row['valor']], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['ok'=>false, 'valor'=>'1.00']);
}
?>
