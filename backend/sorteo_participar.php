<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/conexion.php';

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conn) {
    json_out(['ok' => false, 'msg' => 'Error de conexión con la base de datos'], 500);
}

// === VALIDACIÓN DEFENSIVA DE BASE DE DATOS ===
$chkCol = $conn->query("SHOW COLUMNS FROM `sorteo_participantes` LIKE 'email_enviado'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `sorteo_participantes` ADD COLUMN `email_enviado` TINYINT DEFAULT 0 AFTER `numero_sorteo`");
}
$chkIdx = $conn->query("SHOW INDEX FROM `sorteo_participantes`");
if ($chkIdx) {
    $hasUnique = false;
    while ($row = $chkIdx->fetch_assoc()) {
        if (($row['Key_name'] ?? '') === 'numero_sorteo') {
            $hasUnique = true;
            break;
        }
    }
    if ($hasUnique) {
        @$conn->query("ALTER TABLE `sorteo_participantes` DROP INDEX `numero_sorteo`");
    }
}

// Obtener datos vian JSON o POST
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$email = trim((string)($data['email'] ?? $_POST['email'] ?? ''));
$referido_nombre = trim((string)($data['referido_nombre'] ?? $_POST['referido_nombre'] ?? ''));
$referido_instagram = trim((string)($data['referido_instagram'] ?? $_POST['referido_instagram'] ?? ''));

if ($email === '' || $referido_nombre === '' || $referido_instagram === '') {
    json_out(['ok' => false, 'msg' => 'Faltan datos obligatorios para registrar la participación.']);
}

$emailNorm = mb_strtolower($email);

// Validar Instagram handle (quitar @ si lo ingresó para estandarizar, pero guardarlo limpio)
$referido_instagram = ltrim($referido_instagram, '@');
$referido_instagram = trim($referido_instagram);

if ($referido_instagram === '') {
    json_out(['ok' => false, 'msg' => 'Por favor, ingresá un usuario de Instagram válido.']);
}

// 1. Obtener datos del usuario desde usuarios o profesionales
$nombre = '';
$apellido = '';
$telefono = '';
$encontrado = false;

// Buscar en usuarios
$stmtUser = $conn->prepare("SELECT nombre, apellido, whatsapp FROM usuarios WHERE email = ? LIMIT 1");
if ($stmtUser) {
    $stmtUser->bind_param("s", $emailNorm);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    if ($resUser->num_rows > 0) {
        $user = $resUser->fetch_assoc();
        $nombre = $user['nombre'];
        $apellido = $user['apellido'];
        $telefono = $user['whatsapp'] ?? '';
        $encontrado = true;
    }
    $stmtUser->close();
}

// Si no se encontró, buscar en profesionales
if (!$encontrado) {
    $stmtProf = $conn->prepare("SELECT nombre, apellido, whatsapp FROM profesionales WHERE email = ? LIMIT 1");
    if ($stmtProf) {
        $stmtProf->bind_param("s", $emailNorm);
        $stmtProf->execute();
        $resProf = $stmtProf->get_result();
        if ($resProf->num_rows > 0) {
            $prof = $resProf->fetch_assoc();
            $nombre = $prof['nombre'];
            $apellido = $prof['apellido'];
            $telefono = $prof['whatsapp'] ?? '';
            $encontrado = true;
        }
        $stmtProf->close();
    }
}

if (!$encontrado) {
    json_out(['ok' => false, 'msg' => 'El correo electrónico no corresponde a ningún usuario registrado en la plataforma.']);
}

// 2. Verificar cuántas participaciones tiene actualmente
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM sorteo_participantes WHERE email = ?");
if (!$stmtCount) {
    json_out(['ok' => false, 'msg' => 'Error al verificar participaciones anteriores.']);
}
$stmtCount->bind_param("s", $emailNorm);
$stmtCount->execute();
$resCount = $stmtCount->get_result()->fetch_assoc();
$stmtCount->close();

$actuales = (int)($resCount['total'] ?? 0);
if ($actuales >= 3) {
    json_out(['ok' => false, 'msg' => 'Ya has alcanzado el límite máximo de 3 números asignados para este sorteo.']);
}

// 3. Generar número de sorteo único de 4 cifras (0000 - 9999)
$numero_sorteo = 0;
$max_intentos = 100;
$generado_ok = false;

for ($i = 0; $i < $max_intentos; $i++) {
    $candidato = mt_rand(0, 9999);
    
    // Verificar si el número ya existe en el concurso del mes actual (formato YYYY-MM)
    $candidato_int = (int)$candidato;
    $mesActual = date('Y-m');
    $mesActual_esc = $conn->real_escape_string($mesActual);
    $resChk = $conn->query("SELECT id FROM sorteo_participantes WHERE numero_sorteo = $candidato_int AND DATE_FORMAT(created_at, '%Y-%m') = '$mesActual_esc' LIMIT 1");
    $existe = ($resChk && $resChk->num_rows > 0);
    
    if (!$existe) {
        $numero_sorteo = $candidato;
        $generado_ok = true;
        break;
    }
}

if (!$generado_ok) {
    json_out(['ok' => false, 'msg' => 'No se pudo generar un número de sorteo único en este momento. Por favor intentá de nuevo.']);
}

// 4. Guardar participación en la tabla sorteo_participantes
$stmtInsert = $conn->prepare("INSERT INTO sorteo_participantes (email, telefono, nombre, apellido, referido_nombre, referido_instagram, numero_sorteo) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmtInsert) {
    json_out(['ok' => false, 'msg' => 'Error al preparar el registro del sorteo.']);
}

$stmtInsert->bind_param(
    "ssssssi",
    $emailNorm,
    $telefono,
    $nombre,
    $apellido,
    $referido_nombre,
    $referido_instagram,
    $numero_sorteo
);

if ($stmtInsert->execute()) {
    $insertedId = $stmtInsert->insert_id;
    $stmtInsert->close();

    // Enviar email automáticamente con PHPMailer
    if (file_exists(__DIR__ . '/src/PHPMailer.php') && 
        file_exists(__DIR__ . '/src/SMTP.php') && 
        file_exists(__DIR__ . '/src/Exception.php') &&
        file_exists(__DIR__ . '/config/mailer.env.php')) {
        
        require_once __DIR__ . '/src/PHPMailer.php';
        require_once __DIR__ . '/src/SMTP.php';
        require_once __DIR__ . '/src/Exception.php';
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $config = require __DIR__ . '/config/mailer.env.php';
            $mail->Host = $config['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['SMTP_USER'];
            $mail->Password = $config['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('oscarns@gmail.com', 'BuscoTec');
            $mail->addAddress($emailNorm, "$nombre $apellido");
            $mail->isHTML(true);
            $mail->Subject = 'Tu número para el sorteo - BuscoTec 🎁';

            $numeroFormatted = str_pad((string)$numero_sorteo, 4, '0', STR_PAD_LEFT);
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #1877f2;'>Hola $nombre,</h2>
                    <p style='font-size: 1.1rem; line-height: 1.5;'>Somos de <strong>BuscoTec</strong>.</p>
                    <p style='font-size: 1.2rem; line-height: 1.5; background-color: #f0f7ff; padding: 15px; border-radius: 8px; border-left: 5px solid #1877f2;'>
                        Tu número para el sorteo es el <strong>$numeroFormatted</strong>.
                    </p>
                    <p style='font-size: 1rem; color: #555;'>¡Mucha suerte en el sorteo de Junio 2026!</p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <p style='font-size: 0.85rem; color: #888;'>Este es un mensaje automático. No respondas a este correo.</p>
                </div>
            ";

            $mail->send();
            
            // Actualizar en base de datos que se envió
            $stmtUp = $conn->prepare("UPDATE sorteo_participantes SET email_enviado = 1 WHERE id = ?");
            if ($stmtUp) {
                $stmtUp->bind_param("i", $insertedId);
                $stmtUp->execute();
                $stmtUp->close();
            }
        } catch (Exception $e) {
            error_log("AUTO_MAIL_ERROR_SORTEO: " . $e->getMessage());
        }
    }

    json_out([
        'ok' => true,
        'msg' => '¡Participación registrada con éxito!',
        'numero_sorteo' => str_pad((string)$numero_sorteo, 4, '0', STR_PAD_LEFT),
        'referido_nombre' => $referido_nombre,
        'referido_instagram' => $referido_instagram,
        'cantidad_actual' => $actuales + 1
    ]);
} else {
    // Si falla por restricción única (ej. email único si la tabla no se alteró correctamente)
    $error_msg = $stmtInsert->error;
    $stmtInsert->close();
    if (strpos(strtolower($error_msg), 'duplicate') !== false || strpos($error_msg, '1062') !== false) {
        json_out(['ok' => false, 'is_duplicate' => true, 'msg' => 'Hubo un problema. Si ya registraste este correo, asegurate de que la base de datos permita múltiples registros por correo (ejecutando la consulta ALTER TABLE en tu panel de base de datos).']);
    } else {
        json_out(['ok' => false, 'msg' => 'Error al guardar el sorteo en la base de datos: ' . $error_msg]);
    }
}
