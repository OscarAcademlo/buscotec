<?php
// backend/actualizar_ubicacion.php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . "/session_boot.php";

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$rol = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
$lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

if ($user_id > 0 && $lat != 0) {
    // 1. Tabla Central
    $q1 = $conn->prepare("INSERT INTO ubicaciones_usuarios (user_id, rol, lat, lng, updated_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), updated_at=NOW()");
    $q1->bind_param('isdd', $user_id, $rol, $lat, $lng);
    $q1->execute();

    // 2. Tabla Profesionales
    if ($rol === 'profesional') {
        $q2 = $conn->prepare("UPDATE profesionales SET lat = ?, lng = ? WHERE id = ?");
        $q2->bind_param('ddi', $lat, $lng, $user_id);
        $q2->execute();
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} else {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Datos insuficientes']);
}
exit;
