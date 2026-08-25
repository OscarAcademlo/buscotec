<?php
// backend/admin_enviar_email.php
declare(strict_types=1);

require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . '/cors_helper.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mailer.php';

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

// Validar admin
$emailAdmin = strtolower($_SESSION['email'] ?? '');
if (!in_array($emailAdmin, ['oscarns@gmail.com', 'orticelli@gmail.com'])) {
    fail(403, 'Acceso denegado, requiere administrador');
}

$id = (int)($_POST['id'] ?? 0);
$tipo = trim(strtolower($_POST['tipo'] ?? 'profe'));
$asunto = trim($_POST['asunto'] ?? 'Mensaje de BuscoTec');
$mensaje = trim($_POST['mensaje'] ?? '');

if ($id <= 0 || empty($mensaje)) {
    fail(400, 'Datos incompletos');
}

$tabla = ($tipo === 'user') ? 'usuarios' : 'profesionales';

// Obtener email del destinatario
$emailDestino = '';
$nombreDestino = '';
$stmt = $conn->prepare("SELECT email, nombre, apellido FROM $tabla WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $emailDestino = $row['email'];
        $nombreDestino = $row['nombre'] . ' ' . $row['apellido'];
    }
    $stmt->close();
}

if (empty($emailDestino)) {
    fail(404, 'No se encontró el correo del destinatario en la base de datos');
}

// Convertir los saltos de línea a <br> para el HTML
$mensajeHtml = nl2br(htmlspecialchars($mensaje));

$cuerpoHtml = "
    <div style='font-family: sans-serif; max-width: 600px; margin: auto;'>
        <h2>Hola, $nombreDestino</h2>
        <p>Has recibido un mensaje importante de los administradores de <b>BuscoTec</b>:</p>
        <div style='padding: 15px; border-left: 4px solid #0d6efd; background-color: #f8f9fa; margin: 20px 0;'>
            $mensajeHtml
        </div>
        <p>Por favor, si tenés dudas, podés responder directamente a este correo o contactarnos a través de la web.</p>
        <hr style='border: none; border-top: 1px solid #ccc; margin-top: 30px;' />
        <small style='color: #666;'>Equipo de BuscoTec</small>
    </div>
";

// bt_enviar_mail(string $destinatario, string $asunto, string $mensajeHtml, string $alt = '')
$enviado = bt_enviar_mail($emailDestino, $asunto, $cuerpoHtml);

if ($enviado) {
    echo json_encode(['ok' => true, 'msg' => "Email enviado correctamente a $emailDestino"]);
} else {
    fail(500, "Hubo un error al intentar enviar el email a $emailDestino");
}
