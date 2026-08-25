<?php
// backend/deploy.php — Webhook de despliegue automático para GitHub y Hostinger
header('Content-Type: application/json');

// Opcional: define una clave secreta si deseas validar la firma de GitHub
$secret = getenv('DEPLOY_SECRET') ?: '';

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if ($secret && $signature) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($hash, $signature)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Firma inválida']);
        exit;
    }
}

// Ejecutar git pull en el servidor
$output = [];
$return_code = 0;
exec('cd .. && git pull origin main 2>&1', $output, $return_code);

echo json_encode([
    'ok' => $return_code === 0,
    'output' => $output
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
