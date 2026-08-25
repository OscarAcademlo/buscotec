<?php
// backend/admin_get_all_accounts.php — Traer todos los usuarios y profesionales con su estado
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$res = ["ok" => false, "items" => []];

try {
    // Asegurar columnas suspendido si no existen
    $conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");

    // Profesionales (usamos suspendido para el estado del admin)
    $q_pro = $conn->query("SELECT id, nombre, apellido, email, whatsapp, verificado, email_verificado, suspendido, fecha_registro, 'profe' as tipo FROM profesionales ORDER BY id DESC");
    while($r = $q_pro->fetch_assoc()) { 
        $r['activo'] = (int)$r['suspendido'] === 1 ? 0 : 1;
        $res['items'][] = $r; 
    }
    
    // Usuarios
    $q_usr = $conn->query("SELECT id, nombre, apellido, email, whatsapp, verificado, suspendido, fecha_registro, 'user' as tipo FROM usuarios ORDER BY id DESC");
    while($r = $q_usr->fetch_assoc()) { 
        $r['activo'] = (int)$r['suspendido'] === 1 ? 0 : 1;
        $res['items'][] = $r; 
    }
    
    $res['ok'] = true;
} catch (Throwable $e) {
    $res['error'] = $e->getMessage();
}

echo json_encode($res);
?>
