<?php
// backend/guardar_token_push.php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/conexion.php';

$input = json_decode(file_get_contents('php://input'), true);

$token = $input['token'] ?? '';
$email = $input['email'] ?? '';
$platform = $input['platform'] ?? 'unknown';
$deviceName = $input['device_name'] ?? 'unknown';

if (empty($token) || empty($email)) {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos obligatorios (token o email)']);
    exit;
}

// ⚠️ AUTO-CREAR TABLA SI NO EXISTE
$conn->query("CREATE TABLE IF NOT EXISTS push_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    expo_token VARCHAR(255) NOT NULL,
    platform VARCHAR(50),
    device_name VARCHAR(100),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    UNIQUE KEY unique_token (email, expo_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Usar INSERT ... ON DUPLICATE KEY UPDATE para evitar race conditions y errores de "Duplicate entry"
$stmt = $conn->prepare("
    INSERT INTO push_tokens (email, expo_token, platform, device_name, fecha_registro, fecha_actualizacion) 
    VALUES (?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE 
        platform = VALUES(platform),
        device_name = VALUES(device_name),
        fecha_actualizacion = NOW()
");

$stmt->bind_param("ssss", $email, $token, $platform, $deviceName);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'msg' => 'Token registrado/actualizado correctamente']);
} else {
    error_log("Error registrando token: " . $stmt->error);
    echo json_encode(['ok' => false, 'error' => 'Error BD: ' . $stmt->error]);
}

$conn->close();
?>