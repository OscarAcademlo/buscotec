<?php
// backend/test_push_browser.php
// Versión para ejecutar desde el navegador
// ----------------------------------------------------

// Incluir el boot de sesión primero
require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Webpushr</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            line-height: 1.5;
            background: #f4f4f4;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            margin-top: 0;
            color: #333;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }

        .bg-green {
            background: #28a745;
        }

        .bg-red {
            background: #dc3545;
        }

        .bg-blue {
            background: #007bff;
        }

        pre {
            background: #eee;
            padding: 10px;
            overflow-x: auto;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>🔔 Diagnóstico de Notificaciones</h1>

        <?php
        // 1. Verificar Conexión DB
        if (!isset($conn) || $conn->connect_errno) {
            echo "<div class='badge bg-red'>Error DB</div> <p>No se pudo conectar a la base de datos.</p>";
            exit;
        }
        echo "<p>✅ Base de datos conectada.</p>";

        // 2. Claves (Mostrar parcia)
        $key = '4d4a588e817b482187cee2c9dfb64aec';
        $token = '114560'; // Esto parece corto para un token real, pero así estaba en el código
        
        echo "<p><strong>Key:</strong> " . substr($key, 0, 5) . "•••••<br>";
        echo "<strong>Token:</strong> " . $token . "</p>";

        // 3. Buscar usuario con ID
        echo "<hr><h3>🔍 Buscando suscriptor...</h3>";

        // Intentar buscar mi propio ID si estoy logueado
        $myId = $_SESSION['id'] ?? 0;
        $found = false;
        $targetId = 0;
        $targetRol = '';
        $targetSid = '';

        if ($myId) {
            echo "<p>Sesión activa detectada (ID: $myId). Buscando tu Webpushr ID...</p>";
            // Buscar en usuarios
            $q = $conn->query("SELECT webpushr_id FROM usuarios WHERE id = $myId");
            if ($r = $q->fetch_assoc()) {
                if (!empty($r['webpushr_id'])) {
                    $targetId = $myId;
                    $targetRol = 'usuario';
                    $targetSid = $r['webpushr_id'];
                    $found = true;
                }
            }
            // Buscar en profesionales si no encontró
            if (!$found) {
                $q = $conn->query("SELECT webpushr_id FROM profesionales WHERE id = $myId");
                if ($r = $q->fetch_assoc()) {
                    if (!empty($r['webpushr_id'])) {
                        $targetId = $myId;
                        $targetRol = 'profesional';
                        $targetSid = $r['webpushr_id'];
                        $found = true;
                    }
                }
            }
        }

        // Si no soy yo, buscar cualquiera último
        if (!$found) {
            echo "<p>⚠️ No tienes Webpushr ID en tu usuario actual. Buscando el último registrado en el sistema...</p>";
            $res = $conn->query("SELECT id, 'usuario' as rol, webpushr_id FROM usuarios WHERE webpushr_id IS NOT NULL AND LENGTH(webpushr_id) > 5 ORDER BY id DESC LIMIT 1");
            if ($row = $res->fetch_assoc()) {
                $targetId = $row['id'];
                $targetRol = $row['rol'];
                $targetSid = $row['webpushr_id'];
                $found = true;
            } else {
                $res = $conn->query("SELECT id, 'profesional' as rol, webpushr_id FROM profesionales WHERE webpushr_id IS NOT NULL AND LENGTH(webpushr_id) > 5 ORDER BY id DESC LIMIT 1");
                if ($row = $res->fetch_assoc()) {
                    $targetId = $row['id'];
                    $targetRol = $row['rol'];
                    $targetSid = $row['webpushr_id'];
                    $found = true;
                }
            }
        }

        if (!$found) {
            echo "<div class='badge bg-red'>FALLO</div> <p>No se encontró NINGÚN usuario con webpushr_id en la base de datos. <br>Por favor, navega en la app para registrarte.</p>";
            exit;
        }

        echo "<p>🎯 <strong>Objetivo:</strong> $targetRol ID=$targetId <br>🆔 <strong>SID:</strong> $targetSid</p>";

        // 4. Enviar
        echo "<hr><h3>🚀 Enviando Test...</h3>";

        $payload = [
            'title' => '✅ Test de Sistema',
            'message' => 'Las notificaciones funcionan correctamente.',
            'target_url' => 'https://buscotec.com.ar',
            'sid' => $targetSid
        ];

        $ch = curl_init('https://api.webpushr.com/v1/notification/send/sid');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'webpushrKey: ' . $key,
            'webpushrAuthToken: ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "<pre>HTTP Code: $httpCode\nRespuesta API: $response</pre>";

        $json = json_decode($response, true);
        echo "<br>";

        if ($httpCode == 200 && isset($json['status']) && $json['status'] === 'success') {
            echo "<div class='badge bg-green' style='font-size:1.2em'>¡ÉXITO!</div>";
            echo "<p>✅ La notificación fue aceptada por Webpushr.</p>";
        } else {
            echo "<div class='badge bg-red' style='font-size:1.2em'>ERROR</div>";
            echo "<p>❌ Hubo un problema con la API.</p>";
        }
        ?>

        <p style="margin-top:20px; font-size: 0.9em; color:#666;">
            Nota: Si dice "success" pero no llega, verifica que tengas permisos habilitados en el navegador/Android.
        </p>
    </div>
</body>

</html>