<?php
// backend/sesion_estable.php — versión estable única 2025
declare(strict_types=1);

/*
   Mantiene viva la sesión del usuario para Android / PWA / Safari
   SIN recrearla si el frontend ya borró los datos (logout).
*/

// ---------- Función común para iniciar sesión ----------
require_once __DIR__ . '/session_boot.php';

// ---------- CORS / Headers ----------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://buscotec.click');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Datos que manda el front (si tiene localStorage con user_id/email)
$userId = (int) ($_POST['user_id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');

// ---------- Caso 1: ya hay sesión válida en el servidor ----------
if (!empty($_SESSION['id']) && !empty($_SESSION['email'])) {

    // Si mandan datos, deben coincidir (seguridad)
    if (
        ($userId === 0 || $_SESSION['id'] == $userId) &&
        ($email === '' || $_SESSION['email'] === $email)
    ) {

        // Renovar cookie de sesión 7 días más
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 7,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);

        echo json_encode([
            'ok' => true,
            'msg' => 'Sesión renovada',
            'id' => (int) $_SESSION['id'],
            'role' => $_SESSION['role'] ?? 'usuario'
        ]);
        exit;
    }
}

// ---------- Caso 2: NO hay sesión pero el front tiene datos válidos ---------
// (esto reanima solo si el front aún guarda user_id/email, es decir, NO hizo logout)
if ($userId > 0 && $email !== '') {
    $_SESSION['id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role ?: 'usuario';

    setcookie(session_name(), session_id(), [
        'expires' => time() + 60 * 60 * 24 * 7,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);

    echo json_encode([
        'ok' => true,
        'msg' => 'Sesión reactivada desde datos del cliente',
        'id' => (int) $_SESSION['id'],
        'role' => $_SESSION['role']
    ]);
    exit;
}

// ---------- Caso 3: no hay nada útil ----------
http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'Datos insuficientes o sesión inexistente']);
exit;
