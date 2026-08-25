<?php
// backend/test_session.php
declare(strict_types=1);
require_once __DIR__ . "/session_boot.php";
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'id'    => $_SESSION['id']   ?? null,
    'role'  => $_SESSION['role'] ?? null,
    'email' => $_SESSION['email'] ?? null,
    'nombre'=> $_SESSION['nombre'] ?? null,
    'roles' => $_SESSION['roles'] ?? [],
    'role_ids' => $_SESSION['role_ids'] ?? [],
    'raw'   => $_SESSION
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
