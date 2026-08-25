<?php
// backend/test_session_status.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/boot_sesion.php';

header('Content-Type: text/plain');

echo "Estado de sesión: " . session_status() . " (2 = PHP_SESSION_ACTIVE)\n";
echo "ID Sesión: " . session_id() . "\n";
echo "Cookie Params:\n";
print_r(session_get_cookie_params());
echo "\nDatos \$_SESSION:\n";
print_r($_SESSION);

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;

echo "\nContador (debe subir al refrescar): " . $_SESSION['test_counter'] . "\n";
