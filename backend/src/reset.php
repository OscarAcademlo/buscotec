<?php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['token']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
        exit;
    }

    $token = trim($data['token']);
    $passwordHash = password_hash(trim($data['password']), PASSWORD_DEFAULT);

    // Buscar token en password_resets
    $stmt = $conn->prepare("SELECT email, rol, expires_at, used FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res->num_rows) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Token inválido']);
        exit;
    }

    $row = $res->fetch_assoc();
    $email = $row['email'];
    $rol = $row['rol'];
    $expires_at = strtotime($row['expires_at']);
    $used = (int)$row['used'];

    // Validar token expirado o usado
    if ($used === 1 || $expires_at < time()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Token expirado o ya utilizado']);
        exit;
    }

    // Actualizar contraseña según el rol
    if ($rol === 'profesionales') {
        $sql = "UPDATE profesionales SET password = ? WHERE email = ?";
    } else {
        $sql = "UPDATE usuarios SET password = ? WHERE email = ?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $passwordHash, $email);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Marcar token como usado
        $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        echo json_encode(['ok' => true, 'message' => 'Contraseña restablecida correctamente']);
        exit;
    } else {
        throw new Exception("No se encontró el correo o no se pudo actualizar la contraseña.");
    }

} catch (Throwable $e) {
    error_log("[RESET ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno. Detalles registrados en el log.']);
    exit;
}
?>
