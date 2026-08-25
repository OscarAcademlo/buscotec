<?php
/**
 * enviar_expo_push.php
 * Envía notificaciones push a la App Móvil usando Expo (separado de WebPushr)
 */

require_once __DIR__ . '/conexion.php';

/**
 * Enviar notificación push a través de Expo Push API
 */
function enviarExpoPush($tokens, $titulo, $mensaje, $data = [], $badgeCount = 1)
{
    $url = 'https://exp.host/--/api/v2/push/send';
    $notifications = [];
    foreach ($tokens as $token) {
        // Permitir ambos formatos de token de Expo
        if (!preg_match('/^(ExponentPushToken|ExpoPushToken)\[/', $token))
            continue;
        $notifications[] = [
            'to' => $token,
            'sound' => 'default',
            'title' => $titulo,
            'body' => $mensaje,
            'data' => $data,
            'priority' => 'high',
            'channelId' => 'default',
            'badge' => $badgeCount
        ];
    }

    if (empty($notifications))
        return ['ok' => false];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Accept-Encoding: gzip, deflate'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifications));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['ok' => $httpCode === 200, 'log' => $response];
}

/**
 * Obtener tokens de Expo Push por email desde la tabla push_tokens
 */
function obtenerTokensPorEmail($conn, $email)
{
    $tokens = [];
    $sql = "SELECT expo_token FROM push_tokens WHERE email = ? AND expo_token IS NOT NULL";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tokens[] = $row['expo_token'];
        }
        $stmt->close();
    }
    return $tokens;
}
?>