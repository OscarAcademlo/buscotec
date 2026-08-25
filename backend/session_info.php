<?php
// backend/session_info.php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . "/session_boot.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://buscotec.click');
header('Access-Control-Allow-Credentials: true');

function out(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (empty($_SESSION['email'])) {
    out(['auth' => false, 'error' => 'No hay sesión activa']);
}

// Determinar rol actual
$role = $_SESSION['role'] ?? null;
$id   = $_SESSION['id']   ?? null;

// Si no está definido aún, tomar por defecto
if (!$role || !$id) {
    if (!empty($_SESSION['roles'])) {
        if (in_array('usuario', $_SESSION['roles'], true)) {
            $role = 'usuario';
            $id   = $_SESSION['role_ids']['usuario'] ?? null;
        } elseif (in_array('profesional', $_SESSION['roles'], true)) {
            $role = 'profesional';
            $id   = $_SESSION['role_ids']['profesional'] ?? null;
        }
        // guardar en sesión para siguientes llamadas
        $_SESSION['role'] = $role;
        $_SESSION['id']   = $id;
    }
}

out([
    'auth'     => true,
    'email'    => $_SESSION['email'],
    'nombre'   => $_SESSION['nombre'] ?? '',
    'roles'    => $_SESSION['roles'] ?? [],
    'role_ids' => $_SESSION['role_ids'] ?? [],
    'role'     => $role,
    'id'       => $id
]);
