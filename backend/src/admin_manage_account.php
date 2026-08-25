<?php
// backend/admin_manage_account.php — Suspender o Borrar cuentas
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/session_boot.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar Permisos de Admin
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
$emailSesion = strtolower($_SESSION['email'] ?? '');
if (!in_array($emailSesion, $ADMIN_ALLOWLIST)) {
    echo json_encode(["ok" => false, "msg" => "No autorizado"]);
    exit;
}

if (!($conn instanceof mysqli)) {
    echo json_encode(["ok" => false, "msg" => "Sin conexión"]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? 'profe'; // 'profe' o 'user'
$action = $_POST['action'] ?? 'delete'; // 'delete', 'suspend', 'unsuspend'

if ($id <= 0) {
    echo json_encode(["ok" => false, "msg" => "ID inválido"]);
    exit;
}

$table = ($type === 'user') ? 'usuarios' : 'profesionales';

if ($action === 'delete') {
    $conn->begin_transaction();
    try {
        if ($type === 'profe') {
            // Limpieza de profesional
            $conn->query("DELETE FROM ubicaciones_usuarios WHERE user_id = $id AND rol = 'profesional'");
            $conn->query("DELETE FROM profesional_categorias WHERE profesional_id = $id");
            $conn->query("DELETE FROM calificaciones WHERE profesional_id = $id");
            $conn->query("DELETE FROM casos WHERE receptor_id = $id");
            $conn->query("DELETE FROM mensajes WHERE (remitente_id = $id AND remitente_tipo = 'profesional') OR (destinatario_id = $id AND destinatario_tipo = 'profesional')");
        } else {
            // Limpieza de usuario
            $conn->query("DELETE FROM ubicaciones_usuarios WHERE user_id = $id AND rol = 'usuario'");
            $conn->query("DELETE FROM casos WHERE solicitante_id = $id AND solicitante_tipo = 'usuario'");
            $conn->query("DELETE FROM calificaciones WHERE usuario_id = $id");
            $conn->query("DELETE FROM mensajes WHERE (remitente_id = $id AND remitente_tipo = 'usuario') OR (destinatario_id = $id AND destinatario_tipo = 'usuario')");
        }

        // Eliminar registro principal
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(["ok" => true, "msg" => "Cuenta y datos relacionados eliminados correctamente"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["ok" => false, "msg" => "Error al eliminar: " . $e->getMessage()]);
    }

} elseif ($action === 'suspend' || $action === 'unsuspend') {
    $val = ($action === 'suspend') ? 1 : 0;
    // Asegurar columna suspendido
    $conn->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");
    
    $stmt = $conn->prepare("UPDATE $table SET suspendido = ? WHERE id = ?");
    $stmt->bind_param("ii", $val, $id);
    if ($stmt->execute()) {
        echo json_encode(["ok" => true, "msg" => "Estado de suspensión actualizado"]);
    } else {
        echo json_encode(["ok" => false, "msg" => "Error al actualizar estado"]);
    }
} elseif ($action === 'verify') {
    if ($type === 'user') {
        $stmt = $conn->prepare("UPDATE usuarios SET verificado = 1 WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE profesionales SET email_verificado = 1, verificado = 1, fecha_verificacion = NOW() WHERE id = ?");
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["ok" => true, "msg" => "Cuenta verificada manualmente"]);
    } else {
        echo json_encode(["ok" => false, "msg" => "Error al verificar cuenta"]);
    }
}

?>
