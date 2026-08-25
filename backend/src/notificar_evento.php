<?php
/**
 * notificar_evento.php
 * Script unificado para enviar notificaciones desde el backend
 * Recibe POST con:
 * - tipo: 'mensaje', 'solicitud', 'aceptacion', 'finalizado'
 * - email_destino: Email del usuario que recibe la notificación
 * - titulo: Título de la notificación
 * - mensaje: Cuerpo del mensaje
 * - data: JSON string con datos adicionales (opcional)
 */

header('Content-Type: application/json');

// Permitir CORS de cualquier origen para pruebas
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

require_once 'config.php'; // Asegúrate que config.php conecta a la BD en $conn
require_once 'enviar_push.php';

// Si no tienes config.php a mano, descomenta y configura esto:
/*
$servername = "localhost";
$username = "usuario_db";
$password = "password_db";
$dbname = "nombre_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['ok' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}
*/

// Recibir datos JSON o POST normal
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$tipo = $input['tipo'] ?? '';
$emailDestino = $input['email_destino'] ?? '';
$titulo = $input['titulo'] ?? 'BuscoTec';
$mensaje = $input['mensaje'] ?? 'Tienes una nueva notificación';
$dataExtra = $input['data'] ?? []; // Puede ser array o string JSON

if (is_string($dataExtra)) {
    $dataExtra = json_decode($dataExtra, true) ?? [];
}

if (empty($emailDestino)) {
    echo json_encode(['ok' => false, 'error' => 'Falta email_destino']);
    exit;
}

// Lógica según tipo
$dataFinal = array_merge(['tipo' => $tipo], $dataExtra);

// Si es aceptación de trabajo (Profesional -> Usuario)
if ($tipo === 'aceptacion') {
    // Título y mensaje por defecto si no vienen
    if ($input['titulo'] == 'BuscoTec')
        $titulo = '¡Tu solicitud fue aceptada!';
    if ($input['mensaje'] == 'Tienes una nueva notificación')
        $mensaje = 'Un profesional ha aceptado tu solicitud. Toca para ver detalles.';
    $dataFinal['screen'] = 'EstadoCuenta'; // O la pantalla de detalle correspondiente
}

// Obtener tokens y enviar
$tokens = obtenerTokensPorEmail($conn, $emailDestino);

if (empty($tokens)) {
    echo json_encode(['ok' => false, 'error' => 'Usuario no tiene dispositivos registrados para notificaciones']);
    exit;
}

$resultado = enviarExpoPush($tokens, $titulo, $mensaje, $dataFinal);

echo json_encode($resultado);
?>