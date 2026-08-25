<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$out = [];

// Usuarios
$res = $conn->query("SELECT id, nombre, apellido, email, onesignal_id FROM usuarios ORDER BY id");
$out['usuarios'] = $res->fetch_all(MYSQLI_ASSOC);

// Profesionales
$res = $conn->query("SELECT id, nombre, apellido, email, onesignal_id FROM profesionales ORDER BY id");
$out['profesionales'] = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
