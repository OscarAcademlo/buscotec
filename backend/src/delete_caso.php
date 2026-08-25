<?php
// backend/delete_caso.php — eliminar caso individual
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok'=>false,'error'=>'Sin conexión a la base de datos']);
    exit;
}

session_name('BUSCOTECSESSID');
session_start();
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
$email = strtolower($_SESSION['email'] ?? '');
if (!in_array($email, $ADMIN_ALLOWLIST)) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido']);
    exit;
}

try {
    // 1. Obtener datos antes de borrar
    $stSelect = $conn->prepare("SELECT solicitante_id, receptor_id, cargo_ars FROM casos WHERE id=? LIMIT 1");
    $stSelect->bind_param("i", $id);
    $stSelect->execute();
    $res = $stSelect->get_result()->fetch_assoc();
    $stSelect->close();

    if ($res) {
        $conn->begin_transaction();
        // 2. Registrar en trazabilidad
        $stLog = $conn->prepare("INSERT INTO trazabilidad_casos (caso_id, usuario_id, profesional_id, accion, valor_anterior, admin_email) VALUES (?, ?, ?, 'borrado', ?, ?)");
        $stLog->bind_param("iiids", $id, $res['solicitante_id'], $res['receptor_id'], $res['cargo_ars'], $email);
        $stLog->execute();
        $stLog->close();

        // 3. Borrar
        $stmt = $conn->prepare("DELETE FROM casos WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false, 'error'=>'Caso no encontrado']);
    }
} catch (Throwable $e) {
    if ($conn->in_transaction) $conn->rollback();
    error_log("[delete_caso] ".$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'Error al eliminar']);
}

