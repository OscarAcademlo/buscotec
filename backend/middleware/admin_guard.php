<?php
/**
 * backend/middleware/admin_guard.php
 * Protege rutas/páginas de Admin usando la allowlist.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/../session_boot.php';
}

// Cargar configuración de allowlist
$allowCfg = require __DIR__ . '/../config/allowlist.php';

// Si no se quiere forzar (modo dev), salir sin bloquear
if (empty($allowCfg['enforce'])) {
    return;
}

// 1) Verificar que haya sesión y un email
$userEmail = $_SESSION['user_email'] ?? ''; // <-- ajusta el key si usás otro
$userEmail = mb_strtolower(trim($userEmail));

// 2) Normalizar allowlist
$allowed = array_map(fn($e) => mb_strtolower(trim($e)), $allowCfg['emails'] ?? []);

// 3) ¿Está permitido?
$authorized = $userEmail !== '' && in_array($userEmail, $allowed, true);

// 4) Bloqueo si NO autorizado
if (!$authorized) {
    // Si es endpoint API, devolvé JSON 403
    $isApi = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

    http_response_code(403);

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'FORBIDDEN',
            'message' => 'No tenés permisos para acceder a esta sección.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Si es página, redirigí (o mostrale un 403 simple)
        header('Location: /index.html?err=forbidden'); // <- cambialo si preferís una página 403 dedicada
    }
    exit; // siempre cortar la ejecución
}
