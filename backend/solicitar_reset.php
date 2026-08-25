<?php
declare(strict_types=1);

// ⚙️ Mostrar errores en pantalla para depurar (desactivar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';

// ----------------------------------------------------
// 🔹 PHPMailer (tu estructura es /backend/src/)
// ----------------------------------------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

// ----------------------------------------------------
// 🔹 Configuración SMTP
// ----------------------------------------------------
$configFile = __DIR__ . '/config/mailer.env.php';
$config = file_exists($configFile) ? require $configFile : [];
if (!is_array($config) || empty($config)) {
    echo json_encode(['ok' => false, 'error' => 'Configuración de correo no encontrada']);
    exit;
}

// ----------------------------------------------------
// 🔹 Leer email enviado desde el frontend
// ----------------------------------------------------
if (ob_get_length()) ob_clean();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['email'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Correo no recibido o inválido']);
    exit;
}

$email = trim($input['email']);

// ----------------------------------------------------
// 🔹 Buscar usuario en la base
// ----------------------------------------------------
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Error preparando consulta: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || !$res->num_rows) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'No existe un usuario con ese correo']);
    exit;
}

// ----------------------------------------------------
// 🔹 Generar token y guardar en la tabla actual
// ----------------------------------------------------
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
$rol     = 'usuarios'; // o 'profesionales' si luego lo diferenciás

$stmt = $conn->prepare("INSERT INTO password_resets (email, rol, token, expires_at, used)
                        VALUES (?, ?, ?, ?, 0)");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Error al preparar inserción: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ssss", $email, $rol, $token, $expires);
if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => 'Error al insertar token: ' . $stmt->error]);
    exit;
}

// ----------------------------------------------------
// 🔹 Enlace para restablecer
// ----------------------------------------------------
$reset_link = "https://buscotec.click/reset.html?token=$token";

// ----------------------------------------------------
// 🔹 Enviar correo
// ----------------------------------------------------
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['SMTP_USER'];
    $mail->Password   = $config['SMTP_PASS'];
    $mail->SMTPSecure = $config['SMTP_ENCRYPTION'];
    $mail->Port       = $config['SMTP_PORT'];
    $mail->CharSet    = $config['CHARSET'];

    $mail->setFrom($config['MAIL_FROM'], $config['MAIL_FROM_NAME']);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Recuperar contraseña - BuscoTec';
    $mail->Body = "
        <p>Hola,</p>
        <p>Has solicitado recuperar tu contraseña. Haz clic en el siguiente enlace:</p>
        <p><a href='$reset_link'>$reset_link</a></p>
        <p>Este enlace expirará en 1 hora.</p>
    ";

    $mail->send();

    echo json_encode(['ok' => true, 'message' => 'Correo de recuperación enviado correctamente.']);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo: ' . $e->getMessage()]);
    exit;
}
