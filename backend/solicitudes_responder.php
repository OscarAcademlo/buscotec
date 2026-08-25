<?php
// ============================================================
// backend/solicitudes_responder.php — FINAL 2025 (fix: ambos mensajes de sistema, link solo en aceptado)
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/_api_boot.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/send_fcm_notification.php'; // 🔔 Para notificaciones automáticas a la App
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

function fail(string $msg, int $code = 400): void
{
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ========= SESIÓN ========= */
$userId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0);
if ($userId <= 0)
  fail('Sesión no iniciada', 401);
if (!($conn instanceof mysqli))
  fail('Sin conexión a la base de datos', 500);
$conn->set_charset('utf8mb4');

/* ========= ENTRADA ========= */
$accion = strtolower(trim($_POST['accion'] ?? ''));
$mensajeId = (int) ($_POST['mensaje_id'] ?? 0);
if ($accion === '' || $mensajeId <= 0)
  fail('Datos incompletos');

$estadoNuevo = $accion === 'aceptar' ? 'aceptado' : ($accion === 'rechazar' ? 'rechazado' : '');
if ($estadoNuevo === '')
  fail('Acción no válida');

/* ========= VALIDAR ESTADO ACTUAL ========= */
$q = $conn->prepare("
  SELECT id, estado_respuesta, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo
  FROM mensajes WHERE id=? LIMIT 1
");
$q->bind_param("i", $mensajeId);
$q->execute();
$msg = $q->get_result()->fetch_assoc();
$q->close();
if (!$msg)
  fail('Mensaje no encontrado.');

$estadoActual = strtolower(trim($msg['estado_respuesta'] ?? 'pendiente'));

// Ya aceptado por OTRO
if ($estadoActual === 'aceptado' && (int) $msg['destinatario_id'] !== $userId) {
  fail('Esta solicitud ya fue aceptada por otro profesional.');
}
// Ya aceptado por el MISMO
if ($estadoActual === 'aceptado' && (int) $msg['destinatario_id'] === $userId && $estadoNuevo === 'aceptado') {
  fail('Ya aceptaste este trabajo anteriormente.');
}
// Ya rechazado por el MISMO (no repetir rechazo)
if ($estadoActual === 'rechazado' && $estadoNuevo === 'rechazado') {
  fail('Ya rechazaste este trabajo anteriormente.');
}
// Si rechazó antes, no puede aceptar después
if ($estadoActual === 'rechazado' && $estadoNuevo === 'aceptado') {
  fail('No podés aceptar una solicitud que ya rechazaste.');
}

/* ========= ACTUALIZAR ========= */
$conn->begin_transaction();
$cliente = ['nombre' => '', 'apellido' => '', 'whatsapp' => ''];
try {
  // Estado del mensaje
  $stmt = $conn->prepare("UPDATE mensajes SET estado_respuesta=?, leido=1 WHERE id=? LIMIT 1");
  $stmt->bind_param("si", $estadoNuevo, $mensajeId);
  $stmt->execute();
  $stmt->close();
  // Refuerzo (idempotente)
  $conn->query("UPDATE mensajes SET estado_respuesta='{$estadoNuevo}' WHERE id={$mensajeId} LIMIT 1");

  // Datos de partes
  $usuario_id = (int) $msg['remitente_id'];     // solicitante (quien envió la solicitud)
  $usuario_tipo = (string) $msg['remitente_tipo']; // normalmente 'usuario'
  $prof_id = (int) $msg['destinatario_id'];  // profesional (quien responde)
  $prof_tipo = (string) $msg['destinatario_tipo'];

  // Nombre del profesional (para textos)
  $nombreProfesional = '';
  $categoriaNombre = '';
  $qp = $conn->prepare("
    SELECT p.nombre, p.apellido, c.nombre AS categoria 
    FROM profesionales p 
    LEFT JOIN categorias c ON c.id = p.categoria_id 
    WHERE p.id=? 
    LIMIT 1
  ");
  $qp->bind_param("i", $prof_id);
  $qp->execute();
  $rnp = $qp->get_result()->fetch_assoc();
  $qp->close();
  if ($rnp && !empty($rnp['nombre'])) {
    $nombreProfesional = trim($rnp['nombre'] . ' ' . ($rnp['apellido'] ?? ''));
    $categoriaNombre = $rnp['categoria'] ?? 'Profesional';
  }
  if ($nombreProfesional === '')
    $nombreProfesional = 'El profesional';

  $caso_id = 0;

  // Crear/actualizar caso SOLO si se ACEPTA
  if ($estadoNuevo === 'aceptado') {
    // 💡 VERIFICAR CRÉDITOS DEL PROFESIONAL
    $conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS creditos INT DEFAULT 0");
    $creditos_disponibles = 0;
    $qCred = $conn->query("SELECT creditos FROM profesionales WHERE id={$prof_id} LIMIT 1");
    if ($qCred && $row = $qCred->fetch_assoc()) {
        $creditos_disponibles = (int)$row['creditos'];
    }

    $pagado_state = 0;
    if ($creditos_disponibles > 0) {
        $pagado_state = 1;
        $conn->query("UPDATE profesionales SET creditos = creditos - 1 WHERE id={$prof_id}");
    }

    $stmt2 = $conn->prepare("
      INSERT INTO casos (
        mensaje_id, solicitante_id, solicitante_tipo,
        receptor_id, receptor_tipo, solicitado_por,
        aceptado_por, estado, accepted_at, pagado
      )
      VALUES (?, ?, ?, ?, ?, ?, 'profesional', 'aceptado', NOW(), ?)
      ON DUPLICATE KEY UPDATE estado='aceptado', aceptado_por='profesional', accepted_at=NOW()
    ");
    $solicitado_por = $usuario_tipo;
    $stmt2->bind_param("iissssi", $mensajeId, $usuario_id, $usuario_tipo, $prof_id, $prof_tipo, $solicitado_por, $pagado_state);
    $stmt2->execute();
    $stmt2->close();

    $resCaso = $conn->query("SELECT id FROM casos WHERE mensaje_id={$mensajeId} ORDER BY id DESC LIMIT 1");
    if ($resCaso && $row = $resCaso->fetch_assoc())
      $caso_id = (int) $row['id'];

    // 🚩 FIX: Vincular el caso_id al mensaje original
    if ($caso_id > 0) {
      $conn->query("UPDATE mensajes SET caso_id={$caso_id} WHERE id={$mensajeId} LIMIT 1");
    }
  }

  // ✅ Aplicar cargo_usd dinámico según valor actual en ajustes
  if ($caso_id > 0) {
    $conn->query("
        UPDATE casos
        SET cargo_usd = (
            SELECT CAST(valor AS DECIMAL(10,2))
            FROM ajustes
            WHERE clave = 'valor_caso_usd'
            LIMIT 1
        )
        WHERE id = {$caso_id}
        LIMIT 1
    ");
  }


  $conn->commit();

  /* ========= RESPUESTA BASE ========= */
  $respuesta = [
    'ok' => true,
    'status' => $estadoNuevo,
    'caso_id' => $caso_id
  ];

  // ⚠️ SOLO en ACEPTAR mando datos del cliente para el modal
  if ($estadoNuevo === 'aceptado') {
    $q2 = ($usuario_tipo === 'usuario')
      ? $conn->prepare("SELECT nombre, apellido, whatsapp FROM usuarios WHERE id=? LIMIT 1")
      : $conn->prepare("SELECT nombre, apellido, whatsapp FROM profesionales WHERE id=? LIMIT 1");
    $q2->bind_param("i", $usuario_id);
    $q2->execute();
    $cliDb = $q2->get_result()->fetch_assoc();
    $q2->close();
    if ($cliDb)
      $cliente = $cliDb;
    $respuesta['usuario'] = $cliente;
  }

  /* ========= WEBPUSHR (solo al solicitante) ========= */
  try {
    $WEBPUSHR_KEY = '4d4a588e817b482187cee2c9dfb64aec';
    $WEBPUSHR_AUTH_TOKEN = '114560';
    $WEBPUSHR_ENDPOINT = 'https://api.webpushr.com/v1/notification/send/sid';

    // 🔍 Busca el SID del solicitante, sea usuario o profesional
    $st = $conn->prepare("
    SELECT webpushr_id AS sid
    FROM (
      SELECT webpushr_id FROM usuarios WHERE id=? AND webpushr_id IS NOT NULL
      UNION ALL
      SELECT webpushr_id FROM profesionales WHERE id=? AND webpushr_id IS NOT NULL
    ) AS tmp
    LIMIT 1
  ");
    $st->bind_param('ii', $usuario_id, $usuario_id);

    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();

      $headers = [
        "Content-Type: Application/Json",
        "webpushrKey: {$WEBPUSHR_KEY}",
        "webpushrAuthToken: {$WEBPUSHR_AUTH_TOKEN}"
      ];

    if (!empty($r['sid'])) {
      if ($estadoNuevo === 'aceptado') {
        $title = "✅ {$nombreProfesional} aceptó tu solicitud";
        $body = "{$nombreProfesional} aceptó tu caso y se comunicará a la brevedad.";
        $target = "https://buscotec.com.ar/mensajes.html?caso={$caso_id}";
      } else { // rechazado
        $title = "❌ {$nombreProfesional} rechazó tu solicitud";
        $body = "{$nombreProfesional} rechazó tu solicitud. Podés seguir probando con otros profesionales.";
        $target = "https://buscotec.com.ar/mensajes.html";
      }

      $payload = [
        'sid' => (string) $r['sid'],
        'title' => $title,
        'message' => $body,
        'target_url' => $target,
        'ttl' => 3600
      ];

      $ch = curl_init();
      curl_setopt_array($ch, [
        CURLOPT_URL => $WEBPUSHR_ENDPOINT,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
      ]);
      curl_exec($ch);
      curl_close($ch);
    }

    // 🔍 Busca el SID del PROFESIONAL para notificarle también en web
    $stP = $conn->prepare("SELECT webpushr_id AS sid FROM profesionales WHERE id=? AND webpushr_id IS NOT NULL LIMIT 1");
    $stP->bind_param('i', $prof_id);
    $stP->execute();
    $rP = $stP->get_result()->fetch_assoc();
    $stP->close();

    if ($estadoNuevo === 'aceptado' && !empty($rP['sid'])) {
        $payloadP = [
            'sid' => (string)$rP['sid'],
            'title' => "✅ Datos del Cliente",
            'message' => "Aceptaste el trabajo de {$cliente['nombre']}. Hacé clic para ver su contacto.",
            'target_url' => "https://buscotec.com.ar/mensajes.html",
            'ttl' => 3600
        ];
        $chP = curl_init();
        curl_setopt_array($chP, [
            CURLOPT_URL => $WEBPUSHR_ENDPOINT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payloadP, JSON_UNESCAPED_UNICODE)
        ]);
        curl_exec($chP);
        curl_close($chP);
    }
  } catch (Throwable $tw) {
    error_log('[WEBPUSHR] ' . $tw->getMessage());
  }

  // 🔔 NOTIFICACIÓN PUSH AL SOLICITANTE (Android/iOS)
  try {
    if ($usuario_id > 0) {
      // Buscamos el email del solicitante para asegurar la entrega
      $solicitante_tabla = ($usuario_tipo === 'usuario') ? 'usuarios' : 'profesionales';
      $stS = $conn->prepare("SELECT email FROM $solicitante_tabla WHERE id=? LIMIT 1");
      $stS->bind_param('i', $usuario_id);
      $stS->execute();
      $rS = $stS->get_result()->fetch_assoc();
      $stS->close();
      $solicitanteEmail = $rS['email'] ?? '';

      $fcmTitle = ($estadoNuevo === 'aceptado') ? "✅ Solicitud Aceptada" : "❌ Solicitud Rechazada";
      $fcmBody  = ($estadoNuevo === 'aceptado') 
                  ? "{$nombreProfesional} aceptó tu pedido. Hacé clic para coordinar." 
                  : "{$nombreProfesional} no puede tomar tu pedido. Ya podés buscar a otro profesional.";
      
      $fcmData = [
        "id_caso" => (string)$caso_id,
        "estado"  => (string)$estadoNuevo,
        "tipo"    => "actualizacion_pedido",
        "url"     => "https://buscotec.com.ar/calificar.html?caso=$caso_id"
      ];

      if (!empty($solicitanteEmail)) {
          // 🔔 NOTIFICACIÓN PUSH AL SOLICITANTE
          try {
              sendFCMByEmail($solicitanteEmail, $fcmTitle, $fcmBody, $fcmData);
          } catch (Throwable $e) {
              error_log('[FCM ERROR] ' . $e->getMessage());
          }
          
          // 📧 EMAIL (Opcional - No bloquea si falla)
          try {
              require_once __DIR__ . '/mailer.php';
              $asunto = ($estadoNuevo === 'aceptado') ? "✅ Solicitud Aceptada - BuscoTec" : "❌ Actualización de Solicitud - BuscoTec";
              
              $btnCalificar = "";
              if ($estadoNuevo === 'aceptado' && $caso_id > 0) {
                  $btnCalificar = "<a href='https://buscotec.com.ar/calificar.html?caso={$caso_id}' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Calificar Servicio</a>";
              }

              $msgEmail = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; border: 1px solid #eee; padding: 20px;'>
                    <h2 style='color: " . ($estadoNuevo === 'aceptado' ? '#28a745' : '#dc3545') . ";'>$fcmTitle</h2>
                    <p>Hola,</p>
                    <p>$fcmBody</p>
                    <p>Para ver más detalles o coordinar, usá los siguientes botones:</p>
                    <a href='https://buscotec.com.ar/mensajes.html' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Ver Mensajes</a>
                    $btnCalificar
                    <br><br>
                    <p>Saludos,<br>Equipo de BuscoTec</p>
                </div>";
              bt_enviar_mail($solicitanteEmail, $asunto, $msgEmail);
          } catch (Throwable $mt) {
              error_log('[MAIL ERROR] ' . $mt->getMessage());
          }

      } else {
          sendFCMToUser($usuario_id, $fcmTitle, $fcmBody, $fcmData);
      }
    }
  } catch (Throwable $tn) {
    error_log('[FCM SOLICITANTE] ' . $tn->getMessage());
  }


  /* ========= MENSAJE DE SISTEMA (solo al solicitante; ambos casos) ========= */
  try {
    $direccion = ($usuario_tipo === 'usuario') ? 'pro_to_user' : 'pro_to_prof';

    if ($estadoNuevo === 'aceptado') {
      $calificarUrl = "https://buscotec.com.ar/calificar.html?caso={$caso_id}";
      $msgSis = "✅ <b>{$nombreProfesional} ({$categoriaNombre})</b>. Aceptó tu solicitud de trabajo. Se pondrá en contacto con vos a la brevedad por Whatsapp o llamada telefónica.<br><br>"
        . "⭐ Al finalizar el trabajo, podés calificar acá: "
        . "<a href='{$calificarUrl}'>Calificar</a>";
    } else {
      $msgSis = "❌ {$nombreProfesional} rechazó tu solicitud.<br>"
        . "Podés seguir probando con otros profesionales disponibles.";
    }

    // 🔒 Candado para evitar doble inserción concurrente
    $lockName = "lock_msis_{$prof_id}_{$usuario_id}_{$estadoNuevo}";
    $lk = $conn->prepare("SELECT GET_LOCK(?, 3) AS got");
    $lk->bind_param('s', $lockName);
    $lk->execute();
    $got = (int) ($lk->get_result()->fetch_assoc()['got'] ?? 0);
    $lk->close();

    if ($got === 1) {
      // 🔍 Anti-duplicado: evita repetir el mismo mensaje en los últimos 10 minutos
      $check = $conn->prepare("
      SELECT COUNT(*) AS cnt
      FROM mensajes
      WHERE remitente_id=? 
        AND destinatario_id=? 
        AND tipo='sistema'
        AND estado_respuesta=?
        AND fecha_envio > (NOW() - INTERVAL 10 MINUTE)
    ");
      $check->bind_param('iis', $prof_id, $usuario_id, $estadoNuevo);
      $check->execute();
      $cnt = (int) ($check->get_result()->fetch_assoc()['cnt'] ?? 0);
      $check->close();

      if ($cnt === 0) {
        $sqlSis = "INSERT INTO mensajes (
          remitente_id, remitente_tipo,
          destinatario_id, destinatario_tipo,
          tipo, direccion, mensaje,
          tiene_adjuntos, leido, estado_respuesta, caso_id
        ) VALUES (
          ?, 'profesional',
          ?, ?, 
          'sistema', ?, ?,
          0, 0, ?, ?
        )";
        $destinatario_tipo = (string)$usuario_tipo; 

        $stmtSis = $conn->prepare($sqlSis);
        if ($stmtSis) {
          $stmtSis->bind_param(
            'iissssi',
            $prof_id,
            $usuario_id,
            $destinatario_tipo,
            $direccion,
            $msgSis,
            $estadoNuevo,
            $caso_id
          );
          $stmtSis->execute();
          $stmtSis->close();
        }

        // 🟢 NUEVO: También mandar un mensaje de sistema AL PROFESIONAL con los datos del cliente
        if ($estadoNuevo === 'aceptado') {
           $clienteNombre = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''));
           $clienteWA = $cliente['whatsapp'] ?? '';
           $msgProf = "✅ Aceptaste la solicitud de <b>$clienteNombre</b>.<br>📱 WhatsApp: <a href='https://wa.me/" . preg_replace('/[^0-9]/', '', $clienteWA) . "'>$clienteWA</a>";
           
           $stmtSisP = $conn->prepare("
              INSERT INTO mensajes (
                remitente_id, remitente_tipo,
                destinatario_id, destinatario_tipo,
                tipo, direccion, mensaje,
                tiene_adjuntos, leido, estado_respuesta, caso_id
              ) VALUES (0, 'sistema', ?, 'profesional', 'sistema', 'sys_to_prof', ?, 0, 0, 'aceptado', ?)
           ");
           $stmtSisP->bind_param('isi', $prof_id, $msgProf, $caso_id);
           $stmtSisP->execute();
           $stmtSisP->close();

           // 🔔 NOTIFICACIÓN PUSH AL PROFESIONAL (Android/iOS)
           try {
               // Buscamos el email del profesional para asegurar la entrega
               $stMail = $conn->prepare("SELECT email FROM profesionales WHERE id=? LIMIT 1");
               $stMail->bind_param('i', $prof_id);
               $stMail->execute();
               $rMail = $stMail->get_result()->fetch_assoc();
               $stMail->close();
               $profEmail = $rMail['email'] ?? '';

               $fcmTitleP = "✅ Datos del Cliente";
               $fcmBodyP  = "Aceptaste el trabajo de $clienteNombre. Abrí el mensaje para ver su WhatsApp.";
               $fcmDataP  = [
                   "tipo" => "contacto_cliente",
                   "cliente_wa" => (string)$clienteWA,
                   "caso_id" => (string)$caso_id
               ];
               
               if (!empty($profEmail)) {
                   sendFCMByEmail($profEmail, $fcmTitleP, $fcmBodyP, $fcmDataP);
               } else {
                   sendFCMToUser($prof_id, $fcmTitleP, $fcmBodyP, $fcmDataP);
               }
           } catch (Throwable $tnp) {
               error_log('[FCM PROFESIONAL] ' . $tnp->getMessage());
           }
        }
      }

      // 🔓 Libera el candado
      $rlk = $conn->prepare("SELECT RELEASE_LOCK(?)");
      $rlk->bind_param('s', $lockName);
      $rlk->execute();
      $rlk->close();
    } else {
      // Si no consiguió el lock, no inserta (evita doble mensaje simultáneo)
      error_log('[MENSAJE SISTEMA] Lock no obtenido, se omite inserción duplicada.');
    }

  } catch (Throwable $t) {
    error_log('[ERROR MENSAJE SISTEMA] ' . $t->getMessage());
  }
  echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  error_log("[solicitudes_responder] " . $e->getMessage());
  fail('Error interno al actualizar estado.', 500);
}


?>