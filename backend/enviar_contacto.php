<?php
// ============================================================
// backend/enviar_contacto.php — versión final Gmail App Password
// ============================================================
declare(strict_types=1);
ob_start();

header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Ajuste correcto de rutas (según tus capturas)
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

// === Emails destino (los 2 correctos) ===
$destinatarios = [
  'oscarns@gmail.com',
  'orticelli@gmail.com'
];

// === Leer datos enviados desde el formulario ===
$input = json_decode(file_get_contents('php://input'), true);
$nombre   = trim($input['nombre'] ?? '');
$apellido = trim($input['apellido'] ?? '');
$whatsapp = trim($input['whatsapp'] ?? '');
$email    = trim($input['email'] ?? '');
$motivo   = trim($input['motivo'] ?? '');

if (!$nombre || !$apellido || !$whatsapp || !$email || !$motivo) {
  echo json_encode(['success' => false, 'error' => 'Por favor completá todos los campos.']);
  exit;
}

// === Configuración de Gmail (desde tu mailer.env.php) ===
$mail = new PHPMailer(true);
$mail->SMTPDebug = 2;
$mail->Debugoutput = 'error_log';

try {
  $mail->isSMTP();
  $config = require __DIR__ . '/config/mailer.env.php';
  $mail->Host       = $config['SMTP_HOST'];
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['SMTP_USER']; 
  $mail->Password   = $config['SMTP_PASS'];
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;
  $mail->CharSet    = 'UTF-8';

  // Remitente
  $mail->setFrom('oscarns@gmail.com', 'BuscoTec - Contacto');

  // Destinatarios
  foreach ($destinatarios as $correo) {
    $mail->addAddress($correo);
  }

  // Contenido
  $mail->isHTML(true);
  $mail->Subject = "📩 Nuevo mensaje de contacto - BuscoTec";
  $mail->Body = "
    <h2>Nuevo mensaje recibido desde la página de contacto</h2>
    <p><strong>Nombre:</strong> {$nombre} {$apellido}</p>
    <p><strong>WhatsApp:</strong> {$whatsapp}</p>
    <p><strong>Email:</strong> {$email}</p>
    <p><strong>Mensaje:</strong><br>{$motivo}</p>
    <hr>
    <p style='font-size:12px;color:#777;'>Este correo fue enviado automáticamente desde BuscoTec (via Gmail App Password).</p>
  ";

  // Enviar
  $mail->send();
  echo json_encode(['success' => true]);

} catch (Throwable $e) {
  // 🔍 Log detallado
  error_log('[CONTACTO ERROR] ' . $e->getMessage() . ' - Linea: ' . $e->getLine());
  echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}

