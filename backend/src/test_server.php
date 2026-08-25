<?php
// backend/test_server.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Servidor BuscoTec</h1>";

// 1. Chequeo de Archivos
echo "<h2>1. Verificación de Archivos</h2>";
$files = [
    'conexion.php',
    'guardar_token_push.php',
    'enviar_expo_push.php',
    'enviar_mensaje.php',
    'solicitudes_responder.php'
];

foreach ($files as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        echo "✅ <b>$f</b> existe.<br>";
    } else {
        echo "❌ <b>$f</b> NO EXISTE (Falta subirlo).<br>";
    }
}

// 2. Conexión BD
echo "<h2>2. Conexión a Base de Datos</h2>";
if (!file_exists(__DIR__ . '/conexion.php')) {
    die("❌ No se puede probar BD sin conexion.php");
}
require_once __DIR__ . '/conexion.php';

if ($conn->connect_error) {
    echo "❌ Error de conexión: " . $conn->connect_error;
} else {
    echo "✅ Conexión Exitosa a " . $conn->host_info . "<br>";
}

// 3. Verificar Tabla push_tokens
echo "<h2>3. Verificar Tabla push_tokens</h2>";
$sql = "SELECT count(*) as total FROM push_tokens";
$res = $conn->query($sql);
if ($res) {
    $row = $res->fetch_assoc();
    echo "✅ La tabla existe. Total de dispositivos registrados: <b>" . $row['total'] . "</b><br>";

    if ($row['total'] > 0) {
        $sql2 = "SELECT * FROM push_tokens ORDER BY id DESC LIMIT 5";
        $res2 = $conn->query($sql2);
        echo "<table border='1'><tr><th>ID</th><th>Email</th><th>Token (Inicio)</th><th>Plataforma</th><th>Fecha</th></tr>";
        while ($r = $res2->fetch_assoc()) {
            $tok = substr($r['expo_token'], 0, 20) . "...";
            echo "<tr><td>{$r['id']}</td><td>{$r['email']}</td><td>{$tok}</td><td>{$r['platform']}</td><td>{$r['fecha_registro']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ La tabla está vacía. La app no está logrando registrarse.<br>";
    }
} else {
    echo "❌ Error consultando tabla: " . $conn->error;
}

// 4. Prueba de Envío
echo "<h2>4. Prueba de Envío (Simulación)</h2>";
if (file_exists(__DIR__ . '/enviar_expo_push.php')) {
    require_once __DIR__ . '/enviar_expo_push.php';
    echo "✅ Script de envío cargado.<br>";

    // Buscar un token para probar
    $sql3 = "SELECT expo_token FROM push_tokens ORDER BY id DESC LIMIT 1";
    $res3 = $conn->query($sql3);
    if ($res3 && $row3 = $res3->fetch_assoc()) {
        $tokenPrueba = $row3['expo_token'];
        echo "Concentrando fuego en el último token: $tokenPrueba<br>";

        $resEnvio = enviarExpoPush([$tokenPrueba], "Prueba Servidor", "Si lees esto, el sistema funciona.", ['tipo' => 'test']);

        if ($resEnvio['ok']) {
            echo "✅ <b>ENVÍO EXITOSO</b> desde PHP. Respuesta: <pre>" . htmlspecialchars($resEnvio['log']) . "</pre>";
        } else {
            echo "❌ <b>FALLÓ EL ENVÍO</b>. Respuesta: <pre>" . htmlspecialchars($resEnvio['log']) . "</pre>";
        }
    } else {
        echo "⚠️ No hay tokens para probar envío.";
    }
} else {
    echo "❌ No se puede probar envío (falta enviar_expo_push.php)";
}
?>