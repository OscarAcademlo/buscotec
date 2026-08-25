<?php
declare(strict_types=1);

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

// Obtener datos vian JSON o POST normal
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];
$email = trim((string)($data['email'] ?? $_POST['email'] ?? ''));

if ($email === '') {
    json_out(['ok' => false, 'msg' => 'Por favor, ingresá un correo electrónico.']);
}

$emailNorm = mb_strtolower($email);

if (!filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'msg' => 'El correo electrónico no tiene un formato válido.']);
}

// 1. Verificar si ya está participando en el sorteo (y cuántos números tiene)
$stmtSorteo = $conn->prepare("SELECT nombre, apellido, telefono, referido_nombre, referido_instagram, numero_sorteo, created_at FROM sorteo_participantes WHERE email = ?");
if ($stmtSorteo) {
    $stmtSorteo->bind_param("s", $emailNorm);
    $stmtSorteo->execute();
    $resSorteo = $stmtSorteo->get_result();
    $participaciones = [];
    $nombre = '';
    $apellido = '';
    $telefono = '';

    while ($row = $resSorteo->fetch_assoc()) {
        $participaciones[] = [
            'referido_nombre' => $row['referido_nombre'],
            'referido_instagram' => $row['referido_instagram'],
            'numero_sorteo' => str_pad((string)$row['numero_sorteo'], 4, '0', STR_PAD_LEFT), // Formatear a 4 cifras
            'created_at' => $row['created_at']
        ];
        $nombre = $row['nombre'];
        $apellido = $row['apellido'];
        $telefono = $row['telefono'];
    }
    $stmtSorteo->close();

    if (count($participaciones) > 0) {
        json_out([
            'ok' => true,
            'status' => 'participando',
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono,
            'participaciones' => $participaciones,
            'cantidad' => count($participaciones),
            'maximo_alcanzado' => (count($participaciones) >= 3)
        ]);
    }
}

// 2. Si no participa, buscar en la tabla de usuarios
$stmtUser = $conn->prepare("SELECT nombre, apellido, whatsapp FROM usuarios WHERE email = ? LIMIT 1");
if ($stmtUser) {
    $stmtUser->bind_param("s", $emailNorm);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    if ($resUser->num_rows > 0) {
        $user = $resUser->fetch_assoc();
        $stmtUser->close();
        json_out([
            'ok' => true,
            'status' => 'registrado',
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'telefono' => $user['whatsapp'] ?? ''
        ]);
    }
    $stmtUser->close();
}

// 3. Buscar en la tabla de profesionales
$stmtProf = $conn->prepare("SELECT nombre, apellido, whatsapp FROM profesionales WHERE email = ? LIMIT 1");
if ($stmtProf) {
    $stmtProf->bind_param("s", $emailNorm);
    $stmtProf->execute();
    $resProf = $stmtProf->get_result();
    if ($resProf->num_rows > 0) {
        $prof = $resProf->fetch_assoc();
        $stmtProf->close();
        json_out([
            'ok' => true,
            'status' => 'registrado',
            'nombre' => $prof['nombre'],
            'apellido' => $prof['apellido'],
            'telefono' => $prof['whatsapp'] ?? ''
        ]);
    }
    $stmtProf->close();
}

// 4. Si no está en ningún lado
json_out([
    'ok' => true,
    'status' => 'no_registrado',
    'msg' => 'El correo electrónico ingresado no se encuentra registrado en BuscoTec.'
]);
