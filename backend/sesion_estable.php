<?php
// backend/sesion_estable.php — versión unificada y robusta
declare(strict_types=1);

// 1. Iniciar sesión de forma robusta e idéntica a todo el sistema
require_once __DIR__ . '/session_boot.php';

// 2. Headers CORS Dinámicos
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://buscotec.click");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 3. Lógica de "Restauración"
$userId = (int) ($_POST['user_id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');

// Caso A: La sesión PHP ya está viva
if (!empty($_SESSION['id']) && !empty($_SESSION['email'])) {
    // Si queremos ser estrictos, verificamos que coincida con lo que manda el cliente,
    // pero si ya estamos logueados, suele ser suficiente.
    echo json_encode([
        'ok' => true,
        'msg' => 'Sesión ya activa',
        'id' => (int) $_SESSION['id'],
        'role' => $_SESSION['role'] ?? 'usuario'
    ]);
    exit;
}

// Caso B: Sesión PHP muerta, cliente intenta revivirla con sus datos locales
// (Mantenemos esta lógica por petición del usuario para PWA/Apps)
if ($userId > 0 && $email !== '') {
    $_SESSION['id'] = $userId;
    $_SESSION['user_id'] = $userId; // Compatibilidad crítica
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role ?: 'usuario';

    // session_boot.php ya se encargó de configurar la cookie correctamente.
    // Solo necesitamos confirmar.

    echo json_encode([
        'ok' => true,
        'msg' => 'Sesión restaurada desde cliente',
        'id' => (int) $_SESSION['id'],
        'role' => $_SESSION['role']
    ]);
    exit;
}

// Caso C: Nada funcionó
http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'Datos insuficientes o sesión inexistente']);
