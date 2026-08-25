<?php
// backend/admin_broadcast.php — Envío de mensajes (individual y masivo) + Push Notifications (FCM, Expo, Webpushr)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_broadcast.log');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/enviar_push.php'; // Para Expo

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? 'single'; // 'single' o 'bulk'
$msg = trim($_POST['message'] ?? '');
$target_id = (int)($_POST['target_id'] ?? 0);
$target_type_in = $_POST['target_type'] ?? 'usuario'; // 'user'/'usuario' o 'profe'/'profesional'
$scope = $_POST['scope'] ?? 'all'; // 'all', 'users', 'profes'

// Normalizar tipos para la base de datos
$target_type = ($target_type_in === 'profe' || $target_type_in === 'profesional') ? 'profesional' : 'usuario';

if (empty($msg)) {
    echo json_encode(["ok" => false, "msg" => "El mensaje está vacío"]);
    exit;
}

// Datos del Admin (Sistema)
$remitente_id = 0;
$remitente_tipo = 'sistema';

function dbgAdmin($msg) {
    file_put_contents(__DIR__ . '/debug_mensajes.txt', date('Y-m-d H:i:s') . " | [ADMIN] " . $msg . "\n", FILE_APPEND);
    error_log("[ADMIN_BROADCAST] " . $msg);
}

function notifyAllChannels($conn, $destId, $destType, $destEmail, $text) {
    // destType debe ser 'usuario' o 'profesional'
    $table = ($destType === 'profesional') ? 'profesionales' : 'usuarios';
    
    // 1. Webpushr (Fiel a enviar_notificacion.php que el usuario dice que funciona)
    try {
        $q = $conn->query("SELECT webpushr_id FROM $table WHERE id = $destId");
        if ($q && ($row = $q->fetch_assoc()) && !empty($row['webpushr_id'])) {
            $webpushr_id = trim($row['webpushr_id']);
            
            $req_data = [
                "title"      => "BuscoTec",
                "message"    => $text,
                "target_url" => "https://www.buscotec.com.ar/mensajes.html",
                "sid"        => (string)$webpushr_id
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: Application/Json",
                "webpushrKey: 8d266ba235785924bb013777554cbf67",
                "webpushrAuthToken: 120281"
            ]);
            curl_setopt($ch, CURLOPT_URL, "https://api.webpushr.com/v1/notification/send/sid");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Añadido preventivamente
            
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            dbgAdmin("📢 Webpushr enviado a $webpushr_id -> HTTP $httpcode. Resp: $response");
        } else {
            dbgAdmin("⚠️ Sin webpushr_id para ID $destId ($destType)");
        }
    } catch (Throwable $e) {
        dbgAdmin("❌ EXCEPCIÓN Webpushr: " . $e->getMessage());
    }

    // 2. Expo Push
    if ($destEmail) {
        try {
            $tokens = obtenerTokensPorEmail($conn, $destEmail);
            if (!empty($tokens)) {
                $res = enviarExpoPush($tokens, '📢 Notificación Sistema', $text, ['tipo' => 'sistema', 'screen' => 'Mensajes']);
                dbgAdmin("🚀 Expo Push enviado a " . count($tokens) . " dispositivos: " . json_encode($res));
            }
        } catch (Throwable $e) {
            dbgAdmin("❌ ERROR Expo: " . $e->getMessage());
        }
    }

    // 3. FCM V1 (Copiado de enviar_mensaje.php)
    if ($destEmail) {
        try {
            $qFCM = $conn->prepare("SELECT token FROM push_tokens WHERE (user_id = ? OR email = ?) AND type = 'fcm'");
            $qFCM->bind_param("is", $destId, $destEmail);
            $qFCM->execute();
            $resFCM = $qFCM->get_result();
            $fcmTokens = [];
            while ($rowFCM = $resFCM->fetch_assoc()) { $fcmTokens[] = $rowFCM['token']; }
            $qFCM->close();

            if (!empty($fcmTokens)) {
                $serviceAccountPath = __DIR__ . '/buscotec-fadc9-firebase-adminsdk-fbsvc-9409231e5d.json';
                if (file_exists($serviceAccountPath)) {
                    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
                    $privateKey = $serviceAccount['private_key'];
                    $clientEmail = $serviceAccount['client_email'];
                    $projectId = $serviceAccount['project_id'];

                    // Generar JWT para Auth Google
                    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                    $now = time();
                    $claims = [
                        'iss' => $clientEmail,
                        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                        'aud' => 'https://oauth2.googleapis.com/token',
                        'iat' => $now,
                        'exp' => $now + 3600
                    ];
                    $payloadJWT = base64_encode(json_encode($claims));
                    $unsigned = $header . '.' . $payloadJWT;
                    openssl_sign($unsigned, $signature, openssl_pkey_get_private($privateKey), 'sha256');
                    $jwt = $unsigned . '.' . base64_encode($signature);

                    $chAuth = curl_init('https://oauth2.googleapis.com/token');
                    curl_setopt_array($chAuth, [
                        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
                        CURLOPT_SSL_VERIFYPEER => false
                    ]);
                    $respAuth = curl_exec($chAuth);
                    curl_close($chAuth);
                    $authData = json_decode($respAuth, true);
                    $accessToken = $authData['access_token'] ?? null;

                    if ($accessToken) {
                        foreach ($fcmTokens as $fToken) {
                            $fcmMessage = [
                                "message" => [
                                    "token" => $fToken,
                                    "notification" => ["title" => "📢 Sistema", "body" => $text],
                                    "android" => [
                                        "priority" => "high",
                                        "notification" => [
                                            "channel_id" => "buscotec_alerts_v2", 
                                            "sound" => "default",
                                            "default_sound" => true,
                                            "default_vibrate_timings" => true
                                        ]
                                    ],
                                    "webpush" => [
                                        "fcm_options" => [
                                            "link" => "https://buscotec.com.ar/mensajes.html"
                                        ]
                                    ],
                                    "data" => [
                                        "click_action" => "FLUTTER_NOTIFICATION_CLICK", 
                                        "tipo" => "mensaje_sistema"
                                    ]
                                ]
                            ];
                            $chM = curl_init("https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
                            curl_setopt_array($chM, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken", "Content-Type: application/json"],
                                CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($fcmMessage),
                                CURLOPT_SSL_VERIFYPEER => false
                            ]);
                            $resFCMResult = curl_exec($chM);
                            curl_close($chM);
                            dbgAdmin("🔥 FCM enviado a $fToken: $resFCMResult");
                        }
                    } else {
                        dbgAdmin("❌ No se pudo obtener Access Token para FCM");
                    }
                }
            }
        } catch (Throwable $e) {
            dbgAdmin("❌ ERROR FCM: " . $e->getMessage());
        }
    }
}

if ($action === 'single') {
    $table = ($target_type === 'usuario') ? 'usuarios' : 'profesionales';
    
    // Robusto: seleccionar perfil para obtener el email
    $q = $conn->query("SELECT * FROM $table WHERE id = $target_id");
    $target_email = '';
    if ($q && $r = $q->fetch_assoc()) {
        $target_email = $r['email'] ?? $r['correo'] ?? '';
    }

    $id_global = bin2hex(random_bytes(8));
    $tipoMsg = 'sistema';
    $direccion = 'admin_to_user';
    $tiene_adjuntos = 0;
    $leido = 0;
    $estado_respuesta = 'pendiente';

    try {
        $sql = "INSERT INTO mensajes (
            caso_id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo,
            tipo, id_global, direccion, mensaje, tiene_adjuntos, leido, estado_respuesta, fecha_envio
        ) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare INSERT: " . $conn->error);
        
        $stmt->bind_param("isisssssiis", 
            $remitente_id, $remitente_tipo, $target_id, $target_type, 
            $tipoMsg, $id_global, $direccion, $msg, $tiene_adjuntos, $leido, $estado_respuesta);
        
        if (!$stmt->execute()) throw new Exception("Error en execute INSERT: " . $stmt->error);

        notifyAllChannels($conn, $target_id, $target_type, $target_email, $msg);
        dbgAdmin("✅ Individual enviado a ID $target_id ($target_email)");
        echo json_encode(["ok" => true, "msg" => "Mensaje enviado exitosamente"]);

    } catch (Throwable $e) {
        dbgAdmin("❌ ERROR Individual: " . $e->getMessage());
        echo json_encode(["ok" => false, "msg" => "Error interno: " . $e->getMessage()]);
    }

} elseif ($action === 'bulk') {
    $items = [];
    if ($scope === 'all' || $scope === 'users') {
        $q = $conn->query("SELECT id, email, 'usuario' as tipo FROM usuarios WHERE suspendido = 0");
        if (!$q) $q = $conn->query("SELECT id, email, 'usuario' as tipo FROM usuarios");
        if ($q) while($r = $q->fetch_assoc()) $items[] = $r;
    }
    if ($scope === 'all' || $scope === 'profes') {
        // Usar email o correo según corresponda
        $q = $conn->query("SELECT id, email, 'profesional' as tipo FROM profesionales WHERE suspendido = 0");
        if (!$q) $q = $conn->query("SELECT id, correo as email, 'profesional' as tipo FROM profesionales WHERE suspendido = 0");
        if (!$q) $q = $conn->query("SELECT id, email, 'profesional' as tipo FROM profesionales");
        
        if ($q) while($r = $q->fetch_assoc()) $items[] = $r;
    }

    $count = 0;
    $errors = 0;
    $tipoMsg = 'sistema';
    $direccion = 'admin_to_all';
    $tiene_adjuntos = 0;
    $leido = 0;
    $estado_respuesta = 'pendiente';

    foreach ($items as $item) {
        $id_global = bin2hex(random_bytes(8));
        $destId = (int)$item['id'];
        $destTipo = $item['tipo'];

        try {
            $sql = "INSERT INTO mensajes (
                caso_id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo,
                tipo, id_global, direccion, mensaje, tiene_adjuntos, leido, estado_respuesta, fecha_envio
            ) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isisssssiis", 
                $remitente_id, $remitente_tipo, $destId, $destTipo, 
                $tipoMsg, $id_global, $direccion, $msg, $tiene_adjuntos, $leido, $estado_respuesta);
            
            if ($stmt->execute()) {
                notifyAllChannels($conn, $destId, $destTipo, $item['email'] ?? '', $msg);
                $count++;
            } else {
                $errors++;
            }
        } catch (Throwable $e) {
            $errors++;
            dbgAdmin("❌ Error en bulk ID $destId: " . $e->getMessage());
        }
    }
    
    dbgAdmin("📢 Masivo: $count enviados, $errors errores.");
    echo json_encode(["ok" => true, "msg" => "Enviados: $count. Errores: $errors."]);
}
?>
