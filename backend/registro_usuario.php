<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/conexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/src/Exception.php';

header("Content-Type: application/json");

// ❌ Error conexión
if (!$conn || $conn->connect_errno) {
    echo json_encode(["ok" => false, "msg" => "Error al conectar con MySQL"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["ok" => false, "msg" => "Método inválido"]);
    exit;
}

// === Datos ===
$nombre     = trim($_POST['nombre'] ?? '');
$apellido   = trim($_POST['apellido'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = trim($_POST['password'] ?? '');
$whatsapp   = trim($_POST['whatsapp'] ?? '');
$direccion  = trim($_POST['direccion'] ?? '');
$barrio     = trim($_POST['barrio'] ?? '');
$ciudad     = trim($_POST['ciudad'] ?? '');
$provincia  = trim($_POST['provincia'] ?? '');
$terminos   = isset($_POST['terminos']) ? 1 : 0;

// === Validaciones ===
if ($nombre === '' || $apellido === '' || $email === '' || $password === '' || $whatsapp === '') {
    echo json_encode(["ok" => false, "msg" => "Completá todos los campos obligatorios"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "msg" => "Email inválido"]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(["ok" => false, "msg" => "La contraseña debe tener al menos 6 caracteres"]);
    exit;
}

if (!$terminos) {
    echo json_encode(["ok" => false, "msg" => "Debés aceptar los términos"]);
    exit;
}

// === Email duplicado ===
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["ok" => false, "msg" => "El email ya está registrado"]);
    exit;
}
$stmt->close();

// === Generar ID + Token
$id_global = substr(md5(uniqid(mt_rand(), true)), 0, 8);
$token     = substr(md5(uniqid(mt_rand(), true)), 0, 32);

$hash = password_hash($password, PASSWORD_DEFAULT);
// === Insert FINAL CORREGIDO ===
$sql = $conn->prepare("
    INSERT INTO usuarios
    (id_global, email, password, rol, fecha_registro, verificado, codigo_verificacion,
     nombre, apellido, whatsapp, direccion, barrio, ciudad, provincia)
    VALUES (?,?,?,?,NOW(),0,?,?,?,?,?,?,?,?)

");

$rol = "cliente";

$sql->bind_param(
    "ssssssssssss",
    $id_global,
    $email,
    $hash,
    $rol,
    $token,
    $nombre,
    $apellido,
    $whatsapp,
    $direccion,
    $barrio,
    $ciudad,
    $provincia
);

$sql->execute();
$sql->close();


// === Enviar email ===
$url = "https://buscotec.click/backend/verificar_usuario.php?email=" . urlencode($email) .
       "&token=" . urlencode($token);

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
    $mail->addAddress($email, "$nombre $apellido");
    $mail->isHTML(true);
    $mail->Subject = 'Verificá tu cuenta - BuscoTec';

    $mail->Body = "
        <h2>Hola, $nombre</h2>
        <p>Gracias por registrarte en <b>BuscoTec</b>.</p>
        <p>Para activar tu cuenta, hacé clic en el botón:</p>
        <p><a href='$url' 
           style='background:#0d6efd;color:#fff;padding:12px;border-radius:6px;text-decoration:none;'>
           Verificar cuenta</a></p>
        <br><small>$url</small>
    ";

    $mail->send();

} catch (Exception $e) {
    error_log("MAIL_ERROR: " . $e->getMessage());
}

echo json_encode(["ok" => true, "msg" => "Registro exitoso. Revisá tu email."]);
exit;
?>
