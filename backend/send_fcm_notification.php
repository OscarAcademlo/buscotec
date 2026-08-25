<?php
/**
 * Envía notificación push a través de Firebase Cloud Messaging (FCM)
 * 
 * Uso:
 * require_once 'send_fcm_notification.php';
 * sendFCMNotification($token, 'Título', 'Mensaje', ['url' => 'https://...']);
 */

function sendFCMNotification($token, $title, $body, $data = [])
{
    $serviceAccountPath = __DIR__ . '/buscotec-fadc9-firebase-adminsdk-fbsvc-9409231e5d.json';

    // Verificar que existe el archivo de service account
    if (!file_exists($serviceAccountPath)) {
        error_log('Error: No se encontró firebase-service-account.json');
        return ['ok' => false, 'error' => 'Service account no configurado'];
    }

    // Leer el archivo de service account
    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
    $projectId = $serviceAccount['project_id'];

    // Obtener access token usando JWT
    $accessToken = getAccessToken($serviceAccount);

    if (!$accessToken) {
        return ['ok' => false, 'error' => 'No se pudo obtener access token'];
    }

    // Construir el mensaje
    $message = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'buscotec_alerts_v2',
                    'sound' => 'default',
                    'default_sound' => true
                ]
            ]
        ]
    ];

    // Enviar a FCM
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 🕵️ DEBUG LOG
    error_log("[FCM DEBUG] HTTP: $httpCode | Response: $response");

    $result = json_decode($response, true);

    if ($httpCode === 200) {
        return ['ok' => true, 'response' => $result];
    } else {
        error_log("Error FCM: " . $response);
        return ['ok' => false, 'error' => $result];
    }
}

/**
 * Obtiene un access token de Google usando JWT
 */
function getAccessToken($serviceAccount)
{
    // Crear JWT header
    $header = json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]);

    // Crear JWT claim set
    $now = time();
    $claim = json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    // Codificar en base64url
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));

    // Crear la firma
    if (empty($serviceAccount['private_key'])) {
        error_log('Error: private_key vacía en service account');
        return null;
    }
    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
    if (!$privateKey) {
        error_log('Error: No se pudo obtener la private key de openssl');
        return null;
    }
    openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    // JWT completo
    $jwt = $signatureInput . '.' . $base64UrlSignature;

    // Intercambiar JWT por access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    return $result['access_token'] ?? null;
}

/**
 * Envía notificación a todos los tokens FCM de un usuario
 */
function sendFCMToUser($userId, $title, $body, $data = [])
{
    require_once __DIR__ . '/conexion.php'; 
    global $conn; // 👈 ESTO FALTABA

    try {
        $stmt = $conn->prepare("
            SELECT token FROM push_tokens 
            WHERE user_id = ? AND type = 'fcm'
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $tokens = [];
        while ($row = $res->fetch_assoc()) {
            $tokens[] = $row['token'];
        }
        if (empty($tokens)) {
            error_log("[FCM DEBUG] No se encontraron tokens para el userId: $userId");
            return [];
        }

        $results = [];
        foreach ($tokens as $token) {
            error_log("[FCM DEBUG] Intentando enviar a userId $userId (Token: " . substr($token, 0, 10) . "...)");
            $results[] = sendFCMNotification($token, $title, $body, $data);
        }

        return $results;

    } catch (Throwable $e) {
        error_log("Error enviando FCM: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envía notificación a todos los tokens FCM de un email (más confiable)
 */
function sendFCMByEmail($email, $title, $body, $data = [])
{
    require_once __DIR__ . '/conexion.php';
    if (empty($email)) return [];
    global $conn;

    try {
        $stmt = $conn->prepare("
            SELECT token FROM push_tokens 
            WHERE email = ? AND type = 'fcm'
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $tokens = [];
        while ($row = $res->fetch_assoc()) {
            $tokens[] = $row['token'];
        }
        $stmt->close();

        if (empty($tokens)) {
            error_log("[FCM DEBUG] No se encontraron tokens para el email: $email");
            return [];
        }

        $results = [];
        foreach ($tokens as $token) {
            error_log("[FCM DEBUG] Intentando enviar a $email (Token: " . substr($token, 0, 10) . "...)");
            $results[] = sendFCMNotification($token, $title, $body, $data);
        }
        return $results;
    } catch (Throwable $e) {
        error_log("Error enviando FCM por email ($email): " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
?>