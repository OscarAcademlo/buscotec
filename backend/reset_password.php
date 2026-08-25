<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents("php://input"), true);
$token = trim($input['token'] ?? '');
$password = trim($input['password'] ?? '');

if (!$token || !$password) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

// Buscar token válido
$stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

$row = $res->fetch_assoc();

if ((int)$row['used'] === 1) {
    echo json_encode(['success' => false, 'error' => 'El enlace ya fue utilizado.']);
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    echo json_encode(['success' => false, 'error' => 'Token expirado']);
    exit;
}

$email = $row['email'];
$hash = password_hash($password, PASSWORD_BCRYPT);

// Intentar actualizar en usuarios
$stmt = $conn->prepare("UPDATE usuarios SET password=? WHERE email=?");
$stmt->bind_param("ss", $hash, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    // Si no existe en usuarios, probar en profesionales
    $stmt = $conn->prepare("UPDATE profesionales SET password=? WHERE email=?");
    $stmt->bind_param("ss", $hash, $email);
    $stmt->execute();
}

// Marcar token como usado
$stmt = $conn->prepare("UPDATE password_resets SET used=1 WHERE token=?");
$stmt->bind_param("s", $token);
$stmt->execute();

echo json_encode(['success' => true]);
?>
