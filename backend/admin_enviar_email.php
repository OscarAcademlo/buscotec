<?php
// backend/admin_enviar_email.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void {
  // Retornamos 200 para evitar que Apache/cPanel intercepte el error 500 y devuelva HTML
  echo json_encode(['ok' => false, 'error' => "[$code] $msg"], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
    require_once __DIR__ . '/boot_sesion.php';
    if (file_exists(__DIR__ . '/cors_helper.php')) {
        require_once __DIR__ . '/cors_helper.php';
    } else {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
    require_once __DIR__ . '/conexion.php';
    require_once __DIR__ . '/mailer.php';

    if (!($conn instanceof mysqli)) {
        fail(500, 'Sin conexión a la base de datos');
    }
    $conn->set_charset('utf8mb4');

    // Validar admin
    $emailAdmin = strtolower($_SESSION['email'] ?? '');
    if (!in_array($emailAdmin, ['oscarns@gmail.com', 'orticelli@gmail.com'])) {
        fail(403, 'Acceso denegado, requiere administrador. Sesión actual: ' . ($emailAdmin ?: 'ninguna'));
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
        fail(404, "No se encontró el correo del destinatario (ID: $id, Tipo: $tipo) en la base de datos");
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

    $enviado = bt_enviar_mail($emailDestino, $asunto, $cuerpoHtml);

    if ($enviado) {
        echo json_encode(['ok' => true, 'msg' => "Email enviado correctamente a $emailDestino"]);
    } else {
        echo json_encode([
            'ok' => false,
            'error' => "Hubo un error al intentar enviar el email a $emailDestino. Por favor, verifica la configuración SMTP en mailer.env.php."
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Fatal Error de PHP en el servidor: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ' línea ' . $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
