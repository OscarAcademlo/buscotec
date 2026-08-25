<?php
require_once __DIR__ . '/conexion.php';

// Buscar profesionales llamados Oscar Nicolas
$res = $conn->query("SELECT id, nombre, apellido, lat, lng FROM profesionales WHERE nombre LIKE '%Oscar%' OR apellido LIKE '%Nicolas%'");
echo "=== TABLA profesionales ===\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
    $user_id = $row['id'];
    
    // Buscar en ubicaciones_usuarios
    $res2 = $conn->query("SELECT * FROM ubicaciones_usuarios WHERE user_id = $user_id");
    echo "=== TABLA ubicaciones_usuarios ===\n";
    while($row2 = $res2->fetch_assoc()) {
        print_r($row2);
    }
}
