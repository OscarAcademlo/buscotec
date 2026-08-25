<?php
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/session_boot.php';

header('Content-Type: application/json; charset=utf-8');

// Permisos de admin
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
$email = strtolower($_SESSION['email'] ?? '');
if (!in_array($email, $ADMIN_ALLOWLIST)) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión']);
    exit;
}

$caso_id = (int)($_POST['caso_id'] ?? 0);
$nuevo_valor = (float)str_replace(',', '.', trim($_POST['valor'] ?? ''));

if ($caso_id <= 0 || $nuevo_valor < 0) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

// 1. Obtener valor anterior para trazabilidad
$stmt = $conn->prepare("SELECT cargo_ars, solicitante_id, receptor_id FROM casos WHERE id = ?");
$stmt->bind_param("i", $caso_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode(['ok' => false, 'error' => 'Caso no encontrado']);
    exit;
}

$valor_anterior = (float)$res['cargo_ars'];
$usuario_id = (int)$res['solicitante_id'];
$prof_id = (int)$res['receptor_id'];

$conn->begin_transaction();
try {
    // 2. Actualizar valor
    $st2 = $conn->prepare("UPDATE casos SET cargo_ars = ? WHERE id = ?");
    $st2->bind_param("di", $nuevo_valor, $caso_id);
    $st2->execute();
    $st2->close();

    // 3. Registrar trazabilidad
    $st3 = $conn->prepare("INSERT INTO trazabilidad_casos (caso_id, usuario_id, profesional_id, accion, valor_anterior, valor_nuevo, admin_email) VALUES (?, ?, ?, 'modificado', ?, ?, ?)");
    $st3->bind_param("iiiddds", $caso_id, $usuario_id, $prof_id, $valor_anterior, $nuevo_valor, $email);
    $st3->execute();
    $st3->close();

    $conn->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
