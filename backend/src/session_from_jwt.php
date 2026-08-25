<?php
// ============================================================
// backend/session_from_jwt.php — FIX DEFINITIVO BuscoTec
// ============================================================
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/jwt_helper.php';

// === CLAVE JWT (MISMA QUE EN login.php) ===
$JWT_SECRET = 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026';

// ---------- helper ----------
function out(array $arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ 1) Si la sesión PHP ya existe → listo
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    out([
        'ok'         => true,
        'rehydrated' => false,
        'user_id'    => (int) $_SESSION['user_id'],
        'role'       => $_SESSION['role']
    ]);
}

// ✅ 2) Obtener JWT (cookie o header)
$token = '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$headers = array_change_key_case($headers, CASE_UPPER);

if (!empty($headers['AUTHORIZATION']) && stripos($headers['AUTHORIZATION'], 'Bearer ') === 0) {
    $token = trim(substr($headers['AUTHORIZATION'], 7));
} elseif (!empty($_COOKIE['bt_jwt'])) {
    $token = $_COOKIE['bt_jwt'];
}

if (!$token) {
    out(['ok' => false, 'error' => 'Sin token'], 401);
}

// ✅ 3) Decodificar JWT
try {
    $payload = jwt_decode($token, $JWT_SECRET);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => 'JWT inválido o vencido'], 401);
}

if (empty($payload['uid'])) {
    out(['ok' => false, 'error' => 'JWT sin uid'], 401);
}

// ✅ 4) Rehidratar sesión PHP (SIN tocar cookies)
$_SESSION['id']       = (int) $payload['uid'];
$_SESSION['user_id']  = (int) $payload['uid'];
$_SESSION['email']    = (string) ($payload['email'] ?? '');
$_SESSION['nombre']   = (string) ($payload['nombre'] ?? '');
$_SESSION['role']     = (string) ($payload['role'] ?? 'usuario');
$_SESSION['roles']    = $payload['roles'] ?? [$_SESSION['role']];
$_SESSION['role_ids'] = $payload['role_ids'] ?? [$_SESSION['role'] => $_SESSION['user_id']];

// ❌ NO setcookie acá
// ❌ NO session_regenerate_id
// ❌ NO redefinir sesión

out([
    'ok'         => true,
    'rehydrated' => true,
    'user_id'    => (int) $_SESSION['user_id'],
    'role'       => $_SESSION['role'],
    'roles'      => $_SESSION['roles'],
    'role_ids'   => $_SESSION['role_ids']
]);
