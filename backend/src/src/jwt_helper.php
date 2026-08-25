<?php
// --- JWT NATIVO PARA BUSCOTEC ---
// Sin composer, compatible con Hostinger compartido

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload, string $secret, int $expSeconds = 3600): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload['iat'] = time();                      // issued at
    $payload['exp'] = time() + $expSeconds;        // expiration

    $base64Header  = base64url_encode(json_encode($header));
    $base64Payload = base64url_encode(json_encode($payload));

    $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $secret, true);
    $base64Sig  = base64url_encode($signature);

    return "$base64Header.$base64Payload.$base64Sig";
}

function jwt_decode(string $jwt, string $secret): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;

    [$base64Header, $base64Payload, $base64Sig] = $parts;
    $checkSig = base64url_encode(hash_hmac('sha256', "$base64Header.$base64Payload", $secret, true));

    if (!hash_equals($checkSig, $base64Sig)) return null;

    $payload = json_decode(base64url_decode($base64Payload), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) return null; // expirado o inválido

    return $payload;
}
?>
