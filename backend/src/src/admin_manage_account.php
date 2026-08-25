<?php
// backend/admin_manage_account.php — Suspender o Borrar cuentas
require_once __DIR__ . '/conexion.php';

$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? 'profe'; // 'profe' o 'user'
$action = $_POST['action'] ?? 'delete'; // 'delete', 'suspend', 'unsuspend'

if ($id <= 0) {
    echo json_encode(["ok" => false, "msg" => "ID inválido"]);
    exit;
}

$table = ($type === 'user') ? 'usuarios' : 'profesionales';

if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["ok" => true, "msg" => "Cuenta eliminada"]);
    } else {
        echo json_encode(["ok" => false, "msg" => "Error al eliminar"]);
    }
} elseif ($action === 'suspend' || $action === 'unsuspend') {
    $val = ($action === 'suspend') ? 1 : 0;
    // Asegurar columna suspendido (admin-only)
    $conn->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");
    
    $stmt = $conn->prepare("UPDATE $table SET suspendido = ? WHERE id = ?");
    $stmt->bind_param("ii", $val, $id);
    if ($stmt->execute()) {
        echo json_encode(["ok" => true, "msg" => "Estado de suspensión actualizado"]);
    } else {
        echo json_encode(["ok" => false, "msg" => "Error al actualizar estado"]);
    }
}
?>
