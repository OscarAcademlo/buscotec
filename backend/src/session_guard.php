<?php
declare(strict_types=1);

// Utilizar el boot unificado.
// Esto se encarga de session_start(), nombre de cookie, paths, dominios y secure.
require_once __DIR__ . "/session_boot.php";

// 🔄 Normaliza la sesión para el frontend
function normalize_session(): array
{
    return [
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0,
        'email' => $_SESSION['email'] ?? null,
        'roles' => $_SESSION['roles'] ?? [],
        'role_ids' => $_SESSION['role_ids'] ?? [],
        'nombre' => $_SESSION['nombre'] ?? null,
    ];
}

// 🔐 Requiere sesión activa (sino responde 401)
function require_auth(): array
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autenticado']);
        exit;
    }
    return normalize_session();
}

// 🟡 Devuelve null si no hay sesión (opcional)
function require_auth_soft(): ?array
{
    if (empty($_SESSION['user_id']))
        return null;
    return normalize_session();
}