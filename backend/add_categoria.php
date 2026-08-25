<?php
// ============================================================
// backend/add_categoria.php — versión funcional final 2025
// ============================================================

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

// === Conexión ===
$db = $conn ?? ($conexion ?? null);
if (!$db instanceof mysqli) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión a la base de datos']);
    exit;
}
$db->set_charset('utf8mb4');

// === Admins permitidos ===
$admins = ['oscarns@gmail.com', 'orticelli@gmail.com'];

// === Datos recibidos ===
$nombre = trim($_POST['nombre'] ?? '');
$email  = strtolower(trim($_POST['email'] ?? ''));

// === Validaciones básicas ===
if ($nombre === '') {
    echo json_encode(['ok' => false, 'error' => 'Nombre de categoría vacío']);
    exit;
}

if ($email === '') {
    echo json_encode(['ok' => false, 'error' => 'Email no recibido']);
    exit;
}

// === Verificación de administrador ===
if (!in_array($email, $admins, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado. Iniciá sesión con una cuenta de administrador.']);
    exit;
}

try {
    // Evitar duplicados
    $check = $db->prepare("SELECT id FROM categorias WHERE nombre = ?");
    $check->bind_param('s', $nombre);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        echo json_encode(['ok' => false, 'error' => 'La categoría ya existe']);
        exit;
    }
    $check->close();

    // Insertar nueva categoría
    $stmt = $db->prepare("INSERT INTO categorias (nombre) VALUES (?)");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true, 'msg' => '✅ Categoría agregada correctamente']);
} catch (Throwable $e) {
    error_log('[ADD_CATEGORIA] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error al agregar categoría: ' . $e->getMessage()]);
}
