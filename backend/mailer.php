<?php
// backend/mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

// carga configuración
$configPath = __DIR__ . '/config/mailer.env.php';
$config = file_exists($configPath) ? @require $configPath : [];

/**
 * bt_enviar_mail
 */
function bt_enviar_mail(string $destinatario, string $asunto, string $mensajeHtml, string $alt = ''): bool {
    global $config;
    if (empty($config) || empty($config['SMTP_HOST'])) {
        error_log('[MAIL] Config missing or empty');
        return false;
    }
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['SMTP_USER'];
        $mail->Password   = $config['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = $config['CHARSET'] ?? 'UTF-8';

        $mail->setFrom($config['MAIL_FROM'], $config['MAIL_FROM_NAME'] ?? '');
        $mail->addAddress($destinatario);
        $mail->addReplyTo($config['MAIL_FROM']);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $mensajeHtml;
        $mail->AltBody = $alt ?: strip_tags($mensajeHtml);

        $mail->send();
        error_log("[MAIL] Enviado a $destinatario");
        return true;
    } catch (Exception $e) {
        error_log('[MAIL] Error enviando a ' . $destinatario . ': ' . $mail->ErrorInfo);
        return false;
    }
}
