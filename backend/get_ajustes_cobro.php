<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok'=>false,'error'=>'Sin conexión']);
    exit;
}
$conn->set_charset('utf8mb4');

$res = $conn->query("SELECT clave, valor FROM ajustes WHERE clave IN ('creditos_bienvenida', 'dia_cobro', 'hora_cobro', 'mensaje_extra', 'cron_activado', 'region_cobertura')");
$ajustes = [
    'creditos_bienvenida' => 5,
    'dia_cobro' => 'Lunes',
    'hora_cobro' => '10',
    'mensaje_extra' => '',
    'cron_activado' => 1,
    'region_cobertura' => 'Bariloche, Neuquén, Alto Valle de Río Negro y Neuquén'
];




if ($res) {
    while($row = $res->fetch_assoc()) {
        $ajustes[$row['clave']] = $row['valor'];
    }
}

echo json_encode(['ok'=>true, 'data'=>$ajustes], JSON_UNESCAPED_UNICODE);
?>
