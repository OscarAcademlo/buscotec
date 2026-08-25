<?php
// backend/session_status.php
require_once __DIR__ . "/session_boot.php";
header('Content-Type: application/json; charset=utf-8');

if (!empty($_SESSION['id']) && !empty($_SESSION['rol'])) {
    echo json_encode([
        'ok'   => true,
        'id'   => $_SESSION['id'],
        'rol'  => $_SESSION['rol'],
        'name' => $_SESSION['nombre'] ?? '',
        'email'=> $_SESSION['email'] ?? ''
    ]);
} else {
    echo json_encode(['ok' => false]);
}
