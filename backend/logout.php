<?php
// backend/logout.php — BuscoTec LOGOUT DEFINITIVO
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';

header('Content-Type: application/json; charset=utf-8');

/* Destruir sesión PHP */
$_SESSION = [];
session_unset();
session_destroy();

/* Borrar JWT (login real) */
setcookie('bt_jwt', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'domain'   => '.buscotec.com.ar',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

json_encode(['ok' => true, 'msg' => 'Sesión cerrada']);
echo json_encode(['ok' => true]);
exit;
