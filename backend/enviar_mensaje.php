<?php
// ============================================================
// backend/enviar_mensaje.php — versión estable con Webpushr + adjuntos + ubicación
// ============================================================
declare(strict_types=1);

// 🟢 boot_sesion.php ya inicia la sesión
require_once __DIR__ . '/boot_sesion.php';
if (file_exists(__DIR__ . '/cors_helper.php')) {
    require_once __DIR__ . '/cors_helper.php';
}
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/send_fcm_notification.php'; // 🟢 Usamos la función probada
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-Id, X-User-Role');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.log');

function dbg(string $msg): void
{
  @file_put_contents(__DIR__ . '/debug_mensajes.txt', date('Y-m-d H:i:s') . " | " . $msg . "\n", FILE_APPEND);
}

function fail(int $code, string $msg): void
{
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!($conn instanceof mysqli))
  fail(500, 'Sin conexión a la base de datos');
$conn->set_charset('utf8mb4');

// === DEBUG DE SESIÓN Y POST ===
dbg("🟢 POST=" . json_encode($_POST) . " | SESSION=" . json_encode($_SESSION) . " | COOKIE=" . json_encode($_COOKIE));

try {
  // === Entradas ===
  $mensaje = trim($_POST['mensaje'] ?? '');
  $usuario_id_in = (int) ($_POST['usuario_id'] ?? 0);
  $profesional_id = (int) ($_POST['profesional_id'] ?? 0);
  $caso_id = (int) ($_POST['caso_id'] ?? 0);

  // 🟢 Ubicación opcional
  $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
  $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;

  if ($mensaje === '')
    fail(400, 'Falta texto de mensaje');
  if ($usuario_id_in === 0 && $profesional_id === 0)
    fail(400, 'Debe indicar usuario_id o profesional_id');

  // === Sesión o JWT ===
  $remitente_id = $_SESSION['id'] ?? 0;
  $remitente_tipo = $_SESSION['role'] ?? '';

  if (!$remitente_id || !$remitente_tipo) {
    $jwt_cookie = $_COOKIE['bt_jwt'] ?? '';
    if ($jwt_cookie) {
      require_once __DIR__ . '/jwt_helper.php';
      // Definimos la clave secreta usada en el proyecto
      $JWT_SECRET = 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026';

      try {
        // Usamos la función nativa jwt_decode definida en jwt_helper.php
        $data = jwt_decode($jwt_cookie, $JWT_SECRET);

        if ($data && !empty($data['uid'])) { // El JWT usa 'uid'
          $remitente_id = (int) $data['uid'];
          $remitente_tipo = (string) ($data['role'] ?? 'usuario');
          $_SESSION['id'] = $remitente_id;
          $_SESSION['role'] = $remitente_tipo;
          dbg("✅ Sesión recuperada desde JWT (nativo)");
        } else {
          dbg("⚠️ JWT decodificado pero sin datos válidos (faltó uid)");
        }
      } catch (Throwable $e) {
        dbg("⚠️ JWT error: " . $e->getMessage());
      }
    }
  }

  // === FALLBACK AUTH ===
  // 1) X-User-Id via $_SERVER (confiable en LiteSpeed/Nginx/Apache)
  if (!$remitente_id || !$remitente_tipo) {
    $h_uid = $_SERVER['HTTP_X_USER_ID'] ?? '';
    $h_role = $_SERVER['HTTP_X_USER_ROLE'] ?? '';
    if (($h_uid === '' || $h_role === '') && function_exists('getallheaders')) {
      $hdrs = getallheaders();
      $h_uid = $h_uid ?: ($hdrs['X-User-Id'] ?? $hdrs['x-user-id'] ?? '');
      $h_role = $h_role ?: ($hdrs['X-User-Role'] ?? $hdrs['x-user-role'] ?? '');
    }
    if ($h_uid !== '' && $h_role !== '') {
      $remitente_id = (int) $h_uid;
      $remitente_tipo = (string) $h_role;
      dbg("OK Auth X-User-Id: id=$remitente_id role=$remitente_tipo");
    }
  }

  // 2) Authorization Bearer via $_SERVER
  if (!$remitente_id || !$remitente_tipo) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('getallheaders')) {
      $hh = getallheaders();
      $auth = $hh['Authorization'] ?? $hh['authorization'] ?? '';
    }
    if (str_starts_with($auth, 'Bearer ')) {
      require_once __DIR__ . '/jwt_helper.php';
      try {
        $d = jwt_decode(substr($auth, 7), 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026');
        if ($d && !empty($d['uid'])) {
          $remitente_id = (int) $d['uid'];
          $remitente_tipo = (string) ($d['role'] ?? 'usuario');
          dbg("OK Auth Bearer JWT");
        }
      } catch (Throwable $e) {
        dbg("WARN Bearer: " . $e->getMessage());
      }
    }
  }

  // 3) _uid / _role en POST body (Flutter Web sin preflight CORS)
  if (!$remitente_id || !$remitente_tipo) {
    if (!empty($_POST['_token'])) {
      require_once __DIR__ . '/jwt_helper.php';
      try {
        $d = jwt_decode($_POST['_token'], 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026');
        if ($d && !empty($d['uid'])) {
          $remitente_id = (int) $d['uid'];
          $remitente_tipo = (string) ($d['role'] ?? 'usuario');
          dbg("OK Auth _token body");
        }
      } catch (Throwable $e) {
        dbg("WARN _token body: " . $e->getMessage());
      }
    }
    if (!$remitente_id && !empty($_POST['_uid']) && !empty($_POST['_role'])) {
      $remitente_id = (int) $_POST['_uid'];
      $remitente_tipo = (string) $_POST['_role'];
      dbg("OK Auth _uid body: id=$remitente_id role=$remitente_tipo");
    }
  }

  if (!$remitente_id)
    fail(401, 'No autenticado. _uid_post=' . ($_POST['_uid'] ?? '-') . ' SERVER_UID=' . ($_SERVER['HTTP_X_USER_ID'] ?? '-'));

  // === Normalización destino ===
  $destinatario_id = $usuario_id_in ?: $profesional_id;
  $destinatario_tipo = $usuario_id_in ? 'usuario' : 'profesional';

  if ($remitente_tipo === 'usuario' && $destinatario_tipo === 'profesional') {
    $direccion = 'user_to_pro';
  } elseif ($remitente_tipo === 'profesional' && $destinatario_tipo === 'usuario') {
    $direccion = 'pro_to_user';
  } elseif ($remitente_tipo === 'profesional' && $destinatario_tipo === 'profesional') {
    $direccion = 'pro_to_user';
  } else {
    fail(400, "Combinación remitente=$remitente_tipo destinatario=$destinatario_tipo no permitida");
  }

  $id_global = bin2hex(random_bytes(8));
  $tipo = $remitente_tipo;
  $estado_respuesta = 'pendiente';
  $tiene_adjuntos = 0;
  $leido = 0;

  dbg("🟡 Intento INSERT mensaje");

  // ============================================================
  // INSERT ORIGINAL (NO TOCADO)
  // ============================================================
  $sql = "INSERT INTO mensajes (
      caso_id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo,
      tipo, id_global, direccion, mensaje, tiene_adjuntos, leido, estado_respuesta, fecha_envio
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    'iisisssssiis',
    $caso_id,
    $remitente_id,
    $remitente_tipo,
    $destinatario_id,
    $destinatario_tipo,
    $tipo,
    $id_global,
    $direccion,
    $mensaje,
    $tiene_adjuntos,
    $leido,
    $estado_respuesta
  );
  $stmt->execute();
  $mensaje_id = $stmt->insert_id;
  $stmt->close();

  dbg("✅ Insert OK mensaje_id=$mensaje_id");

  // ============================================================
  // NUEVO: Guardar ubicación (NO toca nada más)
  // ============================================================
  if ($lat !== null && $lng !== null) {
    try {
      $up = $conn->prepare("UPDATE mensajes SET lat=?, lng=? WHERE id=?");
      $up->bind_param("ddi", $lat, $lng, $mensaje_id);
      $up->execute();
      $up->close();
      dbg("📍 Coordenadas guardadas lat=$lat lng=$lng");
    } catch (Throwable $e) {
      dbg("⚠️ Error guardando lat/lng: " . $e->getMessage());
    }
  }

  // ============================================================
  // 📎 ADJUNTOS (NO TOCADO)
  // ============================================================
  try {
    if (isset($_FILES['adjuntos']) && isset($_FILES['adjuntos']['name'])) {
      $names = $_FILES['adjuntos']['name'];
      $tmp = $_FILES['adjuntos']['tmp_name'];
      $errors = $_FILES['adjuntos']['error'];
      $types = $_FILES['adjuntos']['type'];
      $sizes = $_FILES['adjuntos']['size'];

      // Si no es un array (subida individual), lo convertimos en array de 1 elemento
      if (!is_array($names)) {
        $names = [$names];
        $tmp = [$tmp];
        $errors = [$errors];
        $types = [$types];
        $sizes = [$sizes];
      }

      $upload_dir = __DIR__ . '/../uploads_mensajes';
      if (!is_dir($upload_dir)) {
        dbg("⚠️ ERROR: carpeta uploads_mensajes no existe");
      } else {
        $total = count($names);
        $guardados = 0;

        for ($i = 0; $i < $total; $i++) {
          if ($errors[$i] !== UPLOAD_ERR_OK) {
            dbg("⚠️ Adjunto con error de subida código: " . $errors[$i]);
            continue;
          }

          $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION)) ?: 'jpg';
          
          // Robustecer validación de tipo de imagen (MIME o extensión permitida)
          $is_image_type = (strpos($types[$i], 'image/') === 0);
          $is_image_ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

          if (!$is_image_type && !$is_image_ext) {
            dbg("⚠️ Adjunto descartado por tipo/extensión no válida: " . $types[$i] . " (.$ext)");
            continue;
          }

          $nuevo_nombre = "msg_{$mensaje_id}_" . time() . "_$i.$ext";
          $destino_fs = $upload_dir . "/$nuevo_nombre";
          $destino_rel = "uploads_mensajes/$nuevo_nombre";

          if (@move_uploaded_file($tmp[$i], $destino_fs)) {
            $guardados++;
            $mime = $types[$i] ?? 'application/octet-stream';
            // Si el mime provisto es genérico pero es una extensión de imagen conocida, forzamos un mime correcto
            if ($mime === 'application/octet-stream' || empty($mime)) {
              $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            }
            $peso = (int) ($sizes[$i] ?? 0);

            dbg("📎 Imagen guardada: $destino_rel ($mime, $peso bytes)");

            // INSERT en adjuntos (sin created_at para máxima compatibilidad)
            try {
              $sa = $conn->prepare(
                "INSERT INTO mensaje_adjuntos (mensaje_id, ruta, mime, peso)
                 VALUES (?, ?, ?, ?)"
              );
              $sa->bind_param('issi', $mensaje_id, $destino_rel, $mime, $peso);
              $sa->execute();
              $sa->close();
            } catch (Throwable $e) {
              dbg("⚠️ Error insert mensaje_adjuntos: " . $e->getMessage());
            }
          } else {
            dbg("⚠️ Error al mover el archivo subido a $destino_fs");
          }
        }

        if ($guardados > 0) {
          try {
            $u = $conn->prepare("UPDATE mensajes SET tiene_adjuntos=1 WHERE id=?");
            $u->bind_param('i', $mensaje_id);
            $u->execute();
            $u->close();
          } catch (Throwable $e) {
            dbg("⚠️ No se pudo actualizar tiene_adjuntos");
          }
        }
      }
    }
  } catch (Throwable $e) {
    dbg("❌ Error general adjuntos: " . $e->getMessage());
  }

  // ============================================================
  // OBTENER NOMBRE REMITENTE (Para notificaciones)
  // ============================================================
  $nombreRemitente = 'Usuario';
  try {
    $tablaRem = ($remitente_tipo === 'usuario') ? 'usuarios' : 'profesionales';
    if ($tablaRem === 'usuarios' || $tablaRem === 'profesionales') {
      $stmtRem = $conn->prepare("SELECT nombre, apellido FROM $tablaRem WHERE id = ? LIMIT 1");
      $stmtRem->bind_param("i", $remitente_id);
      $stmtRem->execute();
      $resRem = $stmtRem->get_result();
      if ($rowRem = $resRem->fetch_assoc()) {
        $nombreRemitente = trim(($rowRem['nombre'] ?? '') . ' ' . ($rowRem['apellido'] ?? ''));
      }
      $stmtRem->close();
    }
  } catch (Throwable $e) {
    dbg("⚠️ Error nombre remitente: " . $e->getMessage());
  }
  if (empty($nombreRemitente) || $nombreRemitente === 'Usuario') {
    // 🚑 Fallback: intentar usar el email de la sesión si no hay nombre en BD
    $emailSesion = $_SESSION['email'] ?? '';
    if ($emailSesion) {
      $parts = explode('@', $emailSesion);
      if (!empty($parts[0])) {
        $nombreRemitente = $parts[0];
      }
    }
    if (empty($nombreRemitente))
      $nombreRemitente = 'Usuario';
  }
  // Forzar mayúscula inicial
  $nombreRemitente = ucfirst($nombreRemitente);

  // ============================================================
  // 🔔 Webpushr (NO TOCADO) -> AHORA INCLUYE NOMBRE
  // ============================================================
  try {
    $tabla = $destinatario_tipo === 'profesional' ? 'profesionales' : 'usuarios';
    $col = 'webpushr_id';

    $q = $conn->prepare("SELECT $col FROM $tabla WHERE id = ?");
    $q->bind_param("i", $destinatario_id);
    $q->execute();
    $res = $q->get_result();

    if ($res && ($row = $res->fetch_assoc()) && !empty($row[$col])) {
      $webpushr_id = trim($row[$col]);

      $payload = [
        'title' => "📩 $nombreRemitente",
        'message' => $mensaje ?: 'Te enviaron un nuevo mensaje.',
        'target_url' => 'https://buscotec.click/mensajes.html',
        'sid' => $webpushr_id
      ];

      $ch = curl_init('https://api.webpushr.com/v1/notification/send/sid');
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'webpushrKey: 4d4a588e817b482187cee2c9dfb64aec',
        'webpushrAuthToken: 114560'
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
      $resp = curl_exec($ch);
      curl_close($ch);

      dbg("📢 Webpushr enviado a $webpushr_id → Respuesta: $resp");
    } else {
      dbg("⚠️ Sin webpushr_id para $destinatario_tipo $destinatario_id");
    }
  } catch (Throwable $e) {
    dbg("❌ Error Webpushr: " . $e->getMessage());
  }

  // ============================================================
  // 🚀 EXPO PUSH ALERTS (Para App Android/iOS nativa)
  // ============================================================
  try {
    // 1. Obtener el email del destinatario para buscar sus tokens
    $emailDest = '';
    $tablaDest = ($destinatario_tipo === 'usuario') ? 'usuarios' : 'profesionales';
    $stmtEmail = $conn->prepare("SELECT email FROM $tablaDest WHERE id = ? LIMIT 1");
    $stmtEmail->bind_param("i", $destinatario_id);
    $stmtEmail->execute();
    $resEmail = $stmtEmail->get_result();
    if ($rowEmail = $resEmail->fetch_assoc()) {
      $emailDest = $rowEmail['email'] ?? '';
    }
    $stmtEmail->close();

    if ($emailDest) {
      // 2. Buscar tokens de Expo en la tabla push_tokens (la que usa la app)
      $qPush = $conn->prepare("SELECT expo_token FROM push_tokens WHERE email = ?");
      $qPush->bind_param("s", $emailDest);
      $qPush->execute();
      $resPush = $qPush->get_result();

      $expoTokens = [];
      while ($row = $resPush->fetch_assoc()) {
        $expoTokens[] = $row['expo_token'];
      }
      $qPush->close();

      if (!empty($expoTokens)) {
        // 🔹 Obtener el contador REAL de mensajes no leídos para el destinatario
        $unreadCount = 1; // Fallback
        $qCount = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM mensajes 
            WHERE destinatario_id = ? 
              AND (leido = 0 OR leido IS NULL)
        ");
        if ($qCount) {
          $qCount->bind_param("i", $destinatario_id);
          $qCount->execute();
          if ($rC = $qCount->get_result()->fetch_assoc()) {
            $unreadCount = (int) $rC['total'];
          }
          $qCount->close();
        }

        $expoPayload = [];
        foreach ($expoTokens as $token) {
          // Validar formato de token de Expo
          if (strpos($token, 'ExponentPushToken') !== 0 && strpos($token, 'ExpoPushToken') !== 0)
            continue;

          $expoPayload[] = [
            'to' => $token,
            'sound' => 'default',
            'title' => "📩 " . $nombreRemitente,
            'body' => $mensaje ?: 'Tienes un nuevo mensaje.',
            'data' => [
              'tipo' => 'mensaje',
              'remitente' => $nombreRemitente,
              'conversacion_id' => (string)$caso_id,
              'mensaje_id' => (string)$mensaje_id
            ],
            'badge' => $unreadCount,
            'priority' => 'high',
            'channelId' => 'default',
          ];
        }

        if (!empty($expoPayload)) {
          $ch = curl_init('https://exp.host/--/api/v2/push/send');
          curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Encoding: gzip, deflate',
          ]);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($expoPayload));
          $respExpo = curl_exec($ch);
          curl_close($ch);
          dbg("🚀 Expo Push enviado a " . count($expoPayload) . " dispositivos. Respuesta: " . $respExpo);
        }
      } else {
        dbg("⚠️ No se encontraron tokens de Expo para el email: $emailDest");
      }
    } else {
      dbg("⚠️ No se pudo encontrar el email del destinatario ID: $destinatario_id");
    }
  } catch (Throwable $e) {
    dbg("❌ Error Expo Push: " . $e->getMessage());
  }

  // ============================================================
  // 🔥 FIREBASE PUSH (FCM V1) - Usando la función probada
  // ============================================================
  try {
    if ($emailDest) {
      // 1. Buscar tokens de FCM en la tabla push_tokens
      $qFCM = $conn->prepare("SELECT token FROM push_tokens WHERE (user_id = ? OR email = ?) AND type = 'fcm'");
      $qFCM->bind_param("is", $destinatario_id, $emailDest);
      $qFCM->execute();
      $resFCM = $qFCM->get_result();

      $fcmTokens = [];
      while ($rowFCM = $resFCM->fetch_assoc()) {
        $fcmTokens[] = $rowFCM['token'];
      }
      $qFCM->close();

      if (!empty($fcmTokens)) {
        foreach ($fcmTokens as $fToken) {
          $fcm_data = [
            "click_action" => "FLUTTER_NOTIFICATION_CLICK",
            "tipo" => "mensaje_nuevo",
            "caso_id" => (string)$caso_id
          ];
          
          $resFCMResult = sendFCMNotification(
            $fToken, 
            "📩 $nombreRemitente", 
            $mensaje ?: 'Tienes un nuevo mensaje.',
            $fcm_data
          );
          
          dbg("🔥 FCM enviado a $fToken → " . json_encode($resFCMResult));
        }
      }
    }
  } catch (Throwable $e) {
    dbg("❌ Error Critico FCM en Chat: " . $e->getMessage());
  }

  // ============================================================
  // 📧 NOTIFICACIÓN POR EMAIL (Nueva mejora)
  // ============================================================
  try {
    if (!empty($emailDest)) {
      $asunto = "📩 Nuevo mensaje de $nombreRemitente";
      $cuerpoHtml = "
        <div style='font-family: sans-serif; color: #333;'>
          <h2 style='color: #1a3a5c;'>Tienes un nuevo mensaje</h2>
          <p>Hola, <strong>$nombreRemitente</strong> te ha enviado un mensaje en BuscoTec:</p>
          <blockquote style='background: #f0f2f5; padding: 15px; border-radius: 8px; border-left: 4px solid #1877f2;'>
            " . nl2br(htmlspecialchars($mensaje ?: 'Te enviaron un nuevo mensaje.')) . "
          </blockquote>
          <p>Puedes leerlo y responder desde la web o la App:</p>
          <p><a href='https://www.buscotec.com.ar/mensajes.html' style='display: inline-block; background: #1877f2; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 50px; font-weight: bold;'>VER MENSAJE</a></p>
          <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
          <p style='font-size: 12px; color: #888;'>Recibiste este correo porque tienes activadas las notificaciones en tu cuenta de BuscoTec.</p>
        </div>
      ";
      bt_enviar_mail($emailDest, $asunto, $cuerpoHtml);
      dbg("📧 Email enviado a $emailDest");
    }
  } catch (Throwable $e) {
    dbg("⚠️ Error enviando notificación por email: " . $e->getMessage());
  }

  // ============================================================
  // RESPUESTA
  // ============================================================
  echo json_encode([
    'ok' => true,
    'mensaje_id' => $mensaje_id,
    'remitente_id' => $remitente_id,
    'destinatario_id' => $destinatario_id,
    'direccion' => $direccion
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  dbg("❌ EXCEPTION: " . $e->getMessage());
  fail(500, 'Error: ' . $e->getMessage());
}
