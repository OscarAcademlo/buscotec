<?php
// backend/admin_reset_password_link.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void {
    echo json_encode(['ok' => false, 'error' => "[$code] $msg"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/session_boot.php';
    require_once __DIR__ . '/conexion.php';

    if (!($conn instanceof mysqli)) {
        fail(500, 'Sin conexión a la base de datos');
    }
    $conn->set_charset('utf8mb4');

    // 1. Validar admin
    $emailAdmin = strtolower($_SESSION['email'] ?? '');
    $ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
    if (!in_array($emailAdmin, $ADMIN_ALLOWLIST)) {
        fail(403, 'Acceso denegado, requiere administrador.');
    }

    // 2. Obtener parámetros (soportar GET, POST o JSON)
    $input = $_POST;
    if (empty($input)) {
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $input = json_decode($rawInput, true) ?? [];
        }
    }
    if (empty($input)) {
        $input = $_GET;
    }

    $id = (int)($input['id'] ?? 0);
    $type = trim(strtolower($input['type'] ?? $input['tipo'] ?? ''));

    if ($id <= 0 || empty($type)) {
        fail(400, 'Datos incompletos (id o tipo faltante)');
    }

    $tabla = ($type === 'user' || $type === 'usuario') ? 'usuarios' : 'profesionales';
    $rol = ($tabla === 'profesionales') ? 'profesionales' : 'usuarios';

    // 3. Buscar datos del usuario
    $email = '';
    $nombre = '';
    $apellido = '';
    $whatsapp = '';

    $stmt = $conn->prepare("SELECT email, nombre, apellido, whatsapp FROM $tabla WHERE id = ?");
    if (!$stmt) {
        fail(500, 'Error al preparar la consulta: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $email = trim($row['email'] ?? '');
        $nombre = trim($row['nombre'] ?? '');
        $apellido = trim($row['apellido'] ?? '');
        $whatsapp = trim($row['whatsapp'] ?? '');
    }
    $stmt->close();

    if (empty($email)) {
        fail(404, "No se encontró la cuenta con ID $id y tipo $type");
    }

    // 4. Generar token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours')); // Válido por 24 horas

    // 5. Guardar token
    $stmtInsert = $conn->prepare("INSERT INTO password_resets (email, rol, token, expires_at, used) VALUES (?, ?, ?, ?, 0)");
    if (!$stmtInsert) {
        fail(500, 'Error al preparar inserción del token: ' . $conn->error);
    }
    $stmtInsert->bind_param("ssss", $email, $rol, $token, $expires);
    if (!$stmtInsert->execute()) {
        fail(500, 'Error al registrar el token de restablecimiento: ' . $stmtInsert->error);
    }
    $stmtInsert->close();

    // 6. Construir enlace dinámico
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'buscotec.com.ar';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Remover backend/admin_reset_password_link.php de la ruta
    $base_path = str_replace('/backend/admin_reset_password_link.php', '', $script_name);
    if ($base_path === '/') {
        $base_path = '';
    }
    
    $reset_link = "$protocol://$host$base_path/reset.html?token=$token";

    echo json_encode([
        'ok' => true,
        'email' => $email,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'whatsapp' => $whatsapp,
        'token' => $token,
        'link' => $reset_link
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    fail(500, 'Error fatal: ' . $e->getMessage());
}
?>
