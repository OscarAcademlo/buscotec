<?php
/**
 * enviar_push.php
 * Envía notificaciones push usando la API de Expo (SIN Firebase)
 */

/**
 * Enviar notificación push a través de Expo Push API
 * 
 * @param array $tokens Lista de tokens de Expo
 * @param string $titulo Título de la notificación
 * @param string $mensaje Cuerpo de la notificación
 * @param array $data Datos adicionales para la notificación
 * @return array Resultado del envío
 */
function enviarExpoPush($tokens, $titulo, $mensaje, $data = [])
{
    $url = 'https://exp.host/--/api/v2/push/send';

    // Preparar las notificaciones
    $notifications = [];
    foreach ($tokens as $token) {
        // Validar que sea un token de Expo válido
        if (!preg_match('/^ExponentPushToken\[/', $token)) {
            continue; // Saltar tokens inválidos
        }

        $notifications[] = [
            'to' => $token,
            'sound' => 'default',
            'title' => $titulo,
            'body' => $mensaje,
            'data' => $data,
            'priority' => 'high',
            'channelId' => 'default',
            'badge' => 1
        ];
    }

    if (empty($notifications)) {
        return [
            'ok' => false,
            'error' => 'No hay tokens válidos para enviar'
        ];
    }

    // Configurar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Accept-Encoding: gzip, deflate'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifications));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Ejecutar request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Verificar respuesta
    if ($error) {
        return [
            'ok' => false,
            'error' => 'Error de conexión: ' . $error
        ];
    }

    $result = json_decode($response, true);

    return [
        'ok' => $httpCode === 200,
        'http_code' => $httpCode,
        'response' => $result,
        'enviadas' => count($notifications)
    ];
}

/**
 * Obtener tokens de Expo Push por email
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param string $email Email del usuario
 * @return array Lista de tokens
 */
function obtenerTokensPorEmail($conn, $email)
{
    $tokens = [];

    $sql = "SELECT expo_token FROM push_tokens WHERE email = ? AND expo_token IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tokens[] = $row['expo_token'];
    }

    $stmt->close();
    return $tokens;
}

/**
 * Enviar notificación de nuevo mensaje
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param string $destinatarioEmail Email del destinatario
 * @param string $remitenteNombre Nombre del remitente
 * @param int $conversacionId ID de la conversación
 * @param string $remitenteEmail Email del remitente
 */
function notificarNuevoMensaje($conn, $destinatarioEmail, $remitenteNombre, $conversacionId, $remitenteEmail)
{
    $tokens = obtenerTokensPorEmail($conn, $destinatarioEmail);

    if (empty($tokens)) {
        return [
            'ok' => false,
            'error' => 'Usuario no tiene tokens registrados'
        ];
    }

    return enviarExpoPush(
        $tokens,
        '💬 Nuevo mensaje en BuscoTec',
        $remitenteNombre . ' te ha enviado un mensaje',
        [
            'tipo' => 'mensaje',
            'conversacion_id' => $conversacionId,
            'remitente' => $remitenteEmail,
            'screen' => 'DetalleMensaje'
        ]
    );
}

/**
 * Enviar notificación de nueva solicitud
 */
function notificarNuevaSolicitud($conn, $profesionalEmail, $clienteNombre, $servicioNombre)
{
    $tokens = obtenerTokensPorEmail($conn, $profesionalEmail);

    if (empty($tokens)) {
        return ['ok' => false, 'error' => 'Sin tokens'];
    }

    return enviarExpoPush(
        $tokens,
        '🔔 Nueva solicitud de trabajo',
        $clienteNombre . ' te ha enviado una solicitud para: ' . $servicioNombre,
        [
            'tipo' => 'solicitud',
            'screen' => 'EstadoCuenta'
        ]
    );
}

// EJEMPLO DE USO (descomentar para probar):
/*
require_once 'config.php';

// Probar envío
$emailPrueba = 'usuario@ejemplo.com';
$tokens = obtenerTokensPorEmail($conn, $emailPrueba);

if (!empty($tokens)) {
    $resultado = enviarExpoPush(
        $tokens,
        '🔔 Notificación de prueba',
        'Esta es una notificación de prueba desde BuscoTec',
        ['tipo' => 'test', 'timestamp' => time()]
    );
    
    echo json_encode($resultado, JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'No se encontraron tokens para ' . $emailPrueba]);
}

$conn->close();
*/
?>