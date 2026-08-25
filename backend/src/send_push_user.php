<?php
// backend/send_push_user.php

// 1. DEFINE TUS CREDENCIALES DE WEBPUHSR AQUI
// ¡REEMPLAZA ESTOS VALORES!
$api_key    = "TU_CLAVE_API_AQUI";    // Reemplaza con tu API Key
$auth_token = "TU_AUTH_TOKEN_AQUI"; // Reemplaza con tu Auth Token

// 2. RECUPERAR DATOS DEL FORMULARIO
$webpushr_id = filter_input(INPUT_POST, 'webpushr_id', FILTER_SANITIZE_STRING);
$body = filter_input(INPUT_POST, 'body', FILTER_SANITIZE_STRING);
$title = "Mensaje de Buscotec"; // Título

if (!$webpushr_id || !$body) {
    // Si faltan datos, redirige con error
    header('Location: ../admin.php?err=push_failed&details=' . urlencode("Faltan datos (ID de usuario o mensaje)."));
    exit();
}

// 3. PREPARAR DATOS PARA LA API DE WEBPUHSR
$payload = [
    'target_url' => 'https://buscotec.com.ar/mensajes.html', // URL de destino
    
    'notification' => [
        'title' => $title,
        'message' => $body,
    ],
    
    // Envía a IDs específicos
    'target_type' => 'subscriber_ids', 
    'subscriber_ids' => [$webpushr_id], // Envía el ID seleccionado
];

// 4. CONFIGURAR Y HACER LA LLAMADA A LA API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.webpushr.com/api/v1/notification/send');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'webpushrKey: ' . $api_key,
    'webpushrAuthToken: ' . $auth_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. MANEJAR LA RESPUESTA Y REDIRIGIR CON AVISO
if ($http_code === 200 || $http_code === 201) {
    header('Location: ../admin.php?msg=push_sent_user');
    exit();
} else {
    $error_message = json_decode($response, true)['error'] ?? 'Error desconocido';
    header('Location: ../admin.php?err=push_failed&details=' . urlencode("Envío a usuario falló (HTTP $http_code): " . $error_message));
    exit();
}
?>