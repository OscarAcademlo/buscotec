<?php
// backend/session_boot.php — unifica TODA la sesión en BuscoTec 2025
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 365)); // 1 año

session_name('BUSCOTECSESSID');

// Modificado para usar la misma carpeta que el frontend
$path = __DIR__ . '/../tmp_sessions';
if (!file_exists($path)) {
    mkdir($path, 0777, true);
}
session_save_path($path);

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 365,
    'path' => '/',
    'domain' => '.buscotec.com.ar',
    'secure' => true, 
    'httponly' => true,
    'samesite' => 'None'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>