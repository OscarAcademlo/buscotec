<?php
// backend/eliminar_cuenta.php
// Requisito Apple: Eliminar cuenta de usuario/profesional
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id, X-User-Role, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function outApp(array $data): void
{
    if (ob_get_length())
        ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Leer identidad del request
$uid = (int) ($_SERVER['HTTP_X_USER_ID'] ?? $_POST['_uid'] ?? 0);
$role = trim($_SERVER['HTTP_X_USER_ROLE'] ?? $_POST['_role'] ?? 'usuario');

if (!$uid) {
    outApp(['ok' => false, 'error' => 'ID de usuario no proporcionado.']);
}

$success = false;

// 2. Ejecutar eliminación según el rol
if ($role === 'profesional') {
    // Eliminar de profesionales (y sus categorías para evitar errores de integridad)
    $stmt1 = $conn->prepare("DELETE FROM profesional_categorias WHERE profesional_id = ?");
    if ($stmt1) {
        $stmt1->bind_param('i', $uid);
        $stmt1->execute();
        $stmt1->close();
    }
    
    $stmt2 = $conn->prepare("DELETE FROM profesionales WHERE id = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('i', $uid);
        if ($stmt2->execute()) {
            $success = true;
        }
        $stmt2->close();
    }
} else {
    // Eliminar de usuarios
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        if ($stmt->execute()) {
            $success = true;
        }
        $stmt->close();
    }
}

if ($success) {
    outApp(['ok' => true, 'msg' => 'Cuenta eliminada correctamente.']);
} else {
    outApp(['ok' => false, 'error' => 'No se pudo eliminar la cuenta o el usuario no existe.']);
}
