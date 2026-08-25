<?php
// ============================================================
// backend/admin_enviar_mensaje.php — Enviar Push y mensaje a un usuario/profesional desde Admin
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . '/cors_helper.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!($conn instanceof mysqli)) {
    fail(500, 'Sin conexión a la base de datos');
}
$conn->set_charset('utf8mb4');

// Validar que sea admin. Como admin_allowlist está en el frontend,
// idealmente aquí también se debe validar el correo.
$emailAdmin = strtolower($_SESSION['email'] ?? '');
if (!in_array($emailAdmin, ['oscarns@gmail.com', 'orticelli@gmail.com'])) {
    fail(403, 'Acceso denegado, requiere administrador');
}

$destinatario_id = (int)($_POST['destinatario_id'] ?? 0);
$destinatario_tipo = trim(strtolower($_POST['tipo_destinatario'] ?? ''));
$mensaje = trim($_POST['mensaje'] ?? '');

if ($destinatario_id <= 0 || !in_array($destinatario_tipo, ['usuario', 'profesional']) || empty($mensaje)) {
    fail(400, 'Datos incompletos o inválidos');
}

$id_global = bin2hex(random_bytes(8));
$inserted_id = 0;

try {
    // 1) Intentar registrarlo en BD
    // Para no romper las reglas "remitente_tipo", enviaremos como admin pero
    // si la DB requiere 'usuario', le ponemos 0. 
    // Muchos sistemas podrían no tener la enum 'admin'. Probamos.
    try {
        $stmt = $conn->prepare("INSERT INTO mensajes (caso_id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo, tipo, id_global, direccion, mensaje) 
                                VALUES (0, 0, 'admin', ?, ?, 'admin', ?, 'admin_to_user', ?)");
        if ($stmt) {
            $stmt->bind_param("issss", $destinatario_id, $destinatario_tipo, $id_global, $mensaje);
            $stmt->execute();
            $inserted_id = $stmt->insert_id;
            $stmt->close();
        }
    } catch(Throwable $x) {
        // En caso que el enum no soporte "admin", no registramos el mensaje en DB (falla silenciosa)
        // igual mandaremos el PUSH que es lo que realmente importa al admin temporalmente.
        error_log("No se pudo insertar en BD: " . $x->getMessage());
    }

    $nombreRemitente = "BuscoTec (Admin)";

    // 2) Buscar Email del destino
    $emailDest = '';
    $tablaDest = ($destinatario_tipo === 'usuario') ? 'usuarios' : 'profesionales';
    $webpushrId = '';
    
    $stm = $conn->prepare("SELECT email, webpushr_id FROM $tablaDest WHERE id = ?");
    if ($stm) {
        $stm->bind_param("i", $destinatario_id);
        $stm->execute();
        $res = $stm->get_result();
        if ($row = $res->fetch_assoc()) {
            $emailDest = $row['email'];
            $webpushrId = $row['webpushr_id'] ?? '';
        }
        $stm->close();
    }

    // 3) WebPushR
    if (!empty($webpushrId)) {
        $payload = [
            'title' => "📩 $nombreRemitente",
            'message' => $mensaje,
            'target_url' => 'https://www.buscotec.com.ar/mensajes.html',
            'sid' => $webpushrId
        ];
        $ch = curl_init('https://api.webpushr.com/v1/notification/send/sid');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'webpushrKey: 8d266ba235785924bb013777554cbf67',
            'webpushrAuthToken: 120281'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_exec($ch);
        curl_close($ch);
    }

    // 4) Expo Push
    if (!empty($emailDest)) {
        $qPush = $conn->prepare("SELECT expo_token FROM push_tokens WHERE email = ?");
        $qPush->bind_param("s", $emailDest);
        $qPush->execute();
        $resPush = $qPush->get_result();
        $expoTokens = [];
        while ($r = $resPush->fetch_assoc()) {
            if (strpos($r['expo_token'], 'Expo') === 0 || strpos($r['expo_token'], 'Expo') !== false) {
                 $expoTokens[] = $r['expo_token'];
            }
        }
        $qPush->close();

        if (!empty($expoTokens)) {
            $expoPayload = [];
            foreach ($expoTokens as $token) {
                $expoPayload[] = [
                    'to' => $token,
                    'sound' => 'default',
                    'title' => "📩 " . $nombreRemitente,
                    'body' => $mensaje,
                    'priority' => 'high'
                ];
            }
            $ch = curl_init('https://exp.host/--/api/v2/push/send');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
                'Accept-Encoding: gzip, deflate'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($expoPayload));
            curl_exec($ch);
            curl_close($ch);
        }
    }

    // 5) FCM V1
    if (!empty($emailDest)) {
        $qFCM = $conn->prepare("SELECT token FROM push_tokens WHERE (user_id = ? OR email = ?) AND type = 'fcm'");
        $qFCM->bind_param("is", $destinatario_id, $emailDest);
        $qFCM->execute();
        $resFCM = $qFCM->get_result();
        $fcmTokens = [];
        while ($r = $resFCM->fetch_assoc()) {
            $fcmTokens[] = $r['token'];
        }
        $qFCM->close();

        if (!empty($fcmTokens)) {
            $serviceAccountPath = __DIR__ . '/buscotec-fadc9-firebase-adminsdk-fbsvc-9409231e5d.json';
            if (file_exists($serviceAccountPath)) {
                $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
                $privateKey = $serviceAccount['private_key'];
                $clientEmail = $serviceAccount['client_email'];
                $projectId = $serviceAccount['project_id'];

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

                $ch = curl_init('https://oauth2.googleapis.com/token');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt
                    ])
                ]);
                $respAuth = curl_exec($ch);
                curl_close($ch);
                $accessToken = json_decode($respAuth, true)['access_token'] ?? null;

                if ($accessToken) {
                    foreach ($fcmTokens as $fToken) {
                        $fcmMessage = [
                            "message" => [
                                "token" => $fToken,
                                "notification" => [
                                    "title" => "📩 $nombreRemitente",
                                    "body" => $mensaje
                                ],
                                "android" => [
                                    "priority" => "high"
                                ]
                            ]
                        ];
                        $ch = curl_init("https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => [
                                "Authorization: Bearer $accessToken",
                                "Content-Type: application/json"
                            ],
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($fcmMessage)
                        ]);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'mensaje_id' => $inserted_id
    ]);

} catch (Throwable $e) {
    fail(500, 'Error enviando mensaje: ' . $e->getMessage());
}
