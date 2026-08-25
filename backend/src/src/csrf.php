<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { require_once __DIR__ . "/session_boot.php"; }

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(string $token): void {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        echo json_encode(['ok'=>false, 'error'=>'CSRF inválido']);
        exit;
    }
}
