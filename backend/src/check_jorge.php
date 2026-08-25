<?php
// backend/check_jorge.php
require_once 'conexion.php';
header('Content-Type: text/plain; charset=utf-8');

echo "--- REVISANDO ESTADO DE JORGE LÓPEZ ---\n\n";

$nombre = "Jorge";
$apellido = "Lopez";

$q = $conn->prepare("SELECT id, nombre, apellido, verificado, estado_servicio, lat, lng, categoria_id FROM profesionales WHERE nombre LIKE ? AND apellido LIKE ? LIMIT 1");
$n = "%$nombre%";
$a = "%$apellido%";
$q->bind_param("ss", $n, $a);
$q->execute();
$res = $q->get_result()->fetch_assoc();

if (!$res) {
    echo "❌ No encontré a ningún 'Jorge Lopez' en la tabla profesionales.\n";
    exit;
}

echo "ID: " . $res['id'] . "\n";
echo "Nombre: " . $res['nombre'] . " " . $res['apellido'] . "\n";
echo "Verificado: " . ($res['verificado'] == 1 ? "SÍ ✅" : "NO ❌ (Por esto no sale en el mapa)") . "\n";
echo "Estado Servicio: " . ($res['estado_servicio'] == 1 ? "ACTIVO ✅" : "INACTIVO ❌") . "\n";
echo "Coordenadas: Lat " . $res['lat'] . " / Lng " . $res['lng'] . "\n";
echo "ID Categoría: " . $res['categoria_id'] . "\n";

// Revisar también la tabla de ubicaciones
$qu = $conn->prepare("SELECT lat, lng, updated_at FROM ubicaciones_usuarios WHERE user_id = ? AND rol = 'profesional'");
$qu->bind_param("i", $res['id']);
$qu->execute();
$resU = $qu->get_result()->fetch_assoc();

if ($resU) {
    echo "\nUbicación en tabla centralizada:\n";
    echo "Lat " . $resU['lat'] . " / Lng " . $resU['lng'] . " (Actualizado el " . $resU['updated_at'] . ")\n";
} else {
    echo "\n❌ No tiene registro en la tabla ubicaciones_usuarios.\n";
}
?>
