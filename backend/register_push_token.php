<?php
session_start();
header('Content-Type: application/json');

// Obtener datos (compatibilidad JSON y POST estándar)
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$token = $data['token'] ?? '';
$platform = $data['platform'] ?? 'web';
$type = $data['type'] ?? 'fcm'; // Cambiado a fcm por defecto para Flutter

if (empty($token)) {
    echo json_encode(['ok' => false, 'error' => 'Token vacío o formato incorrecto']);
    exit;
}

if (!isset($_SESSION['user_id']) && !isset($_POST['_uid'])) {
    echo json_encode(['ok' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? (int) $_POST['_uid'];

require_once __DIR__ . '/conexion.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión DB']);
    exit;
}

// 2) Autenticación mejorada
$user_id = 0;
if (isset($_SESSION['id'])) {
    $user_id = (int) $_SESSION['id'];
} elseif (!empty($data['_uid'])) {
    $user_id = (int) $data['_uid'];
}

$email = $data['email'] ?? ($_SESSION['email'] ?? '');

if ($user_id <= 0 && empty($email)) {
    echo json_encode(['ok' => false, 'error' => 'Usuario no identificado (falta id y email)']);
    exit;
}

try {
    // 1) Buscar SIEMPRE por el token específico (único por dispositivo/navegador)
    $stmt = $conn->prepare("SELECT id FROM push_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row) {
        // Si el token existe, actualizamos el usuario asociado y la actividad
        $stmt = $conn->prepare("UPDATE push_tokens SET last_active = NOW(), user_id = ?, email = ?, platform = ?, type = ? WHERE id = ?");
        $stmt->bind_param("isssi", $user_id, $email, $platform, $type, $row['id']);
        $success = $stmt->execute();
        if (!$success) {
            throw new Exception("Error al actualizar token: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Si el token es nuevo, lo insertamos sin afectar tokens de otros dispositivos
        $stmt = $conn->prepare("INSERT INTO push_tokens (user_id, email, token, platform, type, last_active, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("issss", $user_id, $email, $token, $platform, $type);
        $success = $stmt->execute();
        if (!$success) {
            throw new Exception("Error al insertar token: " . $stmt->error);
        }
        $stmt->close();
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Token registrado correctamente',
        'type' => $type
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>