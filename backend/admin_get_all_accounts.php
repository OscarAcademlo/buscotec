<?php
// backend/admin_get_all_accounts.php — Traer todos los usuarios y profesionales con su estado
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$res = ["ok" => false, "items" => []];

try {
    // Asegurar columnas si no existen
    @$conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");
    @$conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS creditos INT DEFAULT 0");
    @$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");

    // Traer profesionales priorizando fecha_registro real guardada en el alta
    $sqlPro = "SELECT id, nombre, apellido, email, whatsapp, verificado, suspendido, direccion, barrio, ciudad, COALESCE(NULLIF(fecha_registro, '0000-00-00 00:00:00'), NULLIF(created_at, '0000-00-00 00:00:00')) as fecha_registro, 'profe' as tipo FROM profesionales ORDER BY id DESC";

    $q_pro = $conn->query($sqlPro);
    if ($q_pro) {
        while($r = $q_pro->fetch_assoc()) { 
            $r['activo'] = (int)($r['suspendido'] ?? 0) === 1 ? 0 : 1;
            $res['items'][] = $r; 
        }
    }
    
    // Traer usuarios priorizando fecha_registro real guardada en el alta
    $sqlUsr = "SELECT id, nombre, apellido, email, whatsapp, verificado, suspendido, direccion, barrio, ciudad, COALESCE(NULLIF(fecha_registro, '0000-00-00 00:00:00'), NULLIF(created_at, '0000-00-00 00:00:00')) as fecha_registro, 'user' as tipo FROM usuarios ORDER BY id DESC";

    $q_usr = $conn->query($sqlUsr);
    if ($q_usr) {
        while($r = $q_usr->fetch_assoc()) { 
            $r['activo'] = (int)($r['suspendido'] ?? 0) === 1 ? 0 : 1;
            $res['items'][] = $r; 
        }
    }
    
    $res['ok'] = true;
} catch (Throwable $e) {
    $res['error'] = $e->getMessage();
}

echo json_encode($res);
?>
