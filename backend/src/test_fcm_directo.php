<?php
/**
 * Script para probar el envío de notificaciones FCM directamente.
 * Simplemente abre: https://buscotec.click/backend/test_fcm_directo.php
 */
require_once __DIR__ . '/send_fcm_notification.php';
require_once __DIR__ . '/conexion.php';

echo "<h1>🚀 Probador Directo FCM - BuscoTec</h1>";

// 1. Intentar obtener el último token registrado
$res = $conn->query("SELECT token, email, platform FROM push_tokens WHERE type='fcm' ORDER BY fecha_actualizacion DESC LIMIT 1");
$data = $res->fetch_assoc();

if (!$data) {
    die("❌ No se encontraron tokens FCM en la base de datos.");
}

$token = $data['token'];
$email = $data['email'];
$plataforma = $data['platform'];

echo "<p>Enviando a: <b>$email</b> ($plataforma)</p>";
echo "<p>Token: <small style='color:grey'>$token</small></p>";

// 2. Ejecutar la función de envío (que acabamos de arreglar)
$resultado = sendFCMNotification(
    $token, 
    "🚀 Prueba Directa FCM", 
    "Si recibes esto, el sistema FCM V1 está funcionando correctamente.",
    ["tipo" => "test_directo", "timestamp" => (string)time()]
);

echo "<h3>Resultado:</h3>";
echo "<pre>" . json_encode($resultado, JSON_PRETTY_PRINT) . "</pre>";

if ($resultado['ok']) {
    echo "<h2 style='color:green'>✅ ¡Éxito! Revisa tu teléfono.</h2>";
} else {
    echo "<h2 style='color:red'>❌ Error en el envío.</h2>";
    echo "<p>Verifica si el archivo JSON de Firebase es el correcto.</p>";
}
?>
