<?php
// backend/get_perfil_app.php
// Endpoint dedicado para la App Flutter — autenticación sin sesión PHP
// Valida identidad por: X-User-Id + X-User-Role headers ó GET/POST params
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
// CORS para la app web Flutter
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['http://localhost:3000', 'https://buscotec.com.ar', 'https://oscarsoft.click'], true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id, X-User-Role, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function outApp(array $data): void
{
    if (ob_get_length())
        ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Leer identidad del request
$uid = (int) ($_SERVER['HTTP_X_USER_ID'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
$role = trim($_SERVER['HTTP_X_USER_ROLE'] ?? $_GET['role'] ?? $_POST['role'] ?? 'usuario');
$email = trim($_SERVER['HTTP_X_USER_EMAIL'] ?? $_GET['email'] ?? $_POST['email'] ?? '');

if (!$uid && !$email) {
    outApp(['ok' => false, 'error' => 'ID o email requerido']);
}

// 2. Reconstruir role_ids desde DB
$role_ids = [];
if ($email) {
    $stU = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stU->bind_param('s', $email);
    $stU->execute();
    if ($r = $stU->get_result()->fetch_assoc()) {
        $role_ids['usuario'] = (int) $r['id'];
    }
    $stU->close();

    $stP = $conn->prepare("SELECT id FROM profesionales WHERE email = ? LIMIT 1");
    $stP->bind_param('s', $email);
    $stP->execute();
    if ($r = $stP->get_result()->fetch_assoc()) {
        $role_ids['profesional'] = (int) $r['id'];
    }
    $stP->close();
} else {
    $role_ids[$role] = $uid;
}

if (empty($role_ids)) {
    outApp(['ok' => false, 'error' => 'Usuario no encontrado']);
}

// 3. Obtener datos
function fetchUsuario($conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, nombre, apellido, email, whatsapp, direccion, barrio, ciudad, provincia, fecha_nacimiento, verificado, foto
         FROM usuarios WHERE id = ? LIMIT 1"
    );
    if (!$stmt)
        return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data;
}

function fetchProfesional($conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT p.*, c.nombre as categoria_nombre
         FROM profesionales p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.id = ? LIMIT 1"
    );
    if (!$stmt)
        return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$data)
        return null;

    // Categorías múltiples
    $catStmt = $conn->prepare(
        "SELECT cp.categoria_id, c.nombre
         FROM profesional_categorias cp
         JOIN categorias c ON c.id = cp.categoria_id
         WHERE cp.profesional_id = ? ORDER BY c.nombre"
    );
    if ($catStmt) {
        $catStmt->bind_param('i', $id);
        $catStmt->execute();
        $res = $catStmt->get_result();
        $catIds = [];
        $catNombres = [];
        while ($row = $res->fetch_assoc()) {
            $catIds[] = (int) $row['categoria_id'];
            $catNombres[] = $row['nombre'];
        }
        $catStmt->close();
        $data['categorias'] = $catIds;
        $data['categorias_nombres'] = implode(', ', $catNombres);
        // Legacy fallback
        if (empty($catIds) && !empty($data['categoria_id'])) {
            $data['categorias'][] = (int) $data['categoria_id'];
            $data['categorias_nombres'] = $data['categoria_nombre'] ?? '';
        }
    }
    return $data;
}

$usuario = isset($role_ids['usuario']) ? fetchUsuario($conn, (int) $role_ids['usuario']) : null;
$profesional = isset($role_ids['profesional']) ? fetchProfesional($conn, (int) $role_ids['profesional']) : null;

// 4. Formatear fotos
$fmtFoto = function (string $p, bool $isPro): string {
    $c = ltrim(str_replace(['img/profesionales/', 'img/usuarios/', 'img/'], '', $p), '/');
    return 'https://buscotec.com.ar/img/' . ($isPro ? 'profesionales' : 'usuarios') . '/' . $c;
};
if ($usuario && !empty($usuario['foto']))
    $usuario['foto'] = $fmtFoto($usuario['foto'], false);
if ($profesional && !empty($profesional['foto_profesional']))
    $profesional['foto_profesional'] = $fmtFoto($profesional['foto_profesional'], true);

// Cruzar fotos entre tablas
if ($profesional && empty($profesional['foto_profesional']) && !empty($usuario['foto']))
    $profesional['foto_profesional'] = $usuario['foto'];
if ($usuario && empty($usuario['foto']) && !empty($profesional['foto_profesional']))
    $usuario['foto'] = $profesional['foto_profesional'];

// 5. Verificado unificado
$verificado = 0;
if ($usuario && ((int) ($usuario['verificado'] ?? 0)) === 1)
    $verificado = 1;
if ($profesional && ((int) ($profesional['verificado'] ?? 0)) === 1)
    $verificado = 1;
if ($usuario)
    $usuario['verificado'] = $verificado;
if ($profesional)
    $profesional['verificado'] = $verificado;

outApp([
    'ok' => true,
    'data' => [
        'role_ids' => $role_ids,
        'roles' => array_keys($role_ids),
        'usuario' => $usuario,
        'profesional' => $profesional,
    ],
]);
