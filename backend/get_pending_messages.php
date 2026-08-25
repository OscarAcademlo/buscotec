<?php
// backend/get_pending_messages.php
declare(strict_types=1);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';

// (Opcional) JWT si lo necesitás
require_once __DIR__ . '/jwt_helper.php';
$JWT_SECRET = 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026';

$out = ['pending_messages' => 0];

// 1) Resolver user_id por orden: GET → SESSION → JWT
$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);    // algunos endpoints tuyos usan 'user_id'
}
if ($userId <= 0) {
    $userId = (int) ($_SESSION['id'] ?? 0);         // en BuscoTec principal usás 'id'
}
if ($userId <= 0 && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (!empty($headers['Authorization'])) {
        [$type, $jwt] = explode(' ', $headers['Authorization'], 2);
        if ($type === 'Bearer' && $jwt) {
            $payload = jwt_decode($jwt, $JWT_SECRET);
            if ($payload) {
                $userId = (int) ($payload['uid'] ?? $payload['user_id'] ?? 0);
            }
        }
    }
}
if ($userId <= 0) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) Resolver roles (usuario/profesional) para filtrar correctamente
$role_ids = $_SESSION['role_ids'] ?? [];
$is_usuario = isset($role_ids['usuario']) && $role_ids['usuario'] > 0;
$is_profesional = isset($role_ids['profesional']) && $role_ids['profesional'] > 0;

if (!$is_usuario && !$is_profesional && $userId > 0) {
    // Fallback: Si no hay roles en sesión, intentar inferir (aunque lo ideal es que estén)
    $is_usuario = true; // Por defecto asumimos usuario si solo hay un ID
}

$totalPending = 0;

function countForRole(mysqli $db, int $id, string $tipo): int {
    $sql = "SELECT COUNT(*) AS n 
            FROM mensajes 
            WHERE destinatario_id = ? 
              AND destinatario_tipo = ? 
              AND COALESCE(eliminado,0) = 0 
              AND COALESCE(leido,0) = 0 
              AND (contenido IS NOT NULL OR mensaje IS NOT NULL)";
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('is', $id, $tipo);
    $stmt->execute();
    $res = $stmt->get_result();
    $n = ($res && ($row = $res->fetch_assoc())) ? (int)$row['n'] : 0;
    $stmt->close();
    return $n;
}

if ($is_usuario) {
    $totalPending += countForRole($conn, $role_ids['usuario'] ?? $userId, 'usuario');
}
if ($is_profesional) {
    $totalPending += countForRole($conn, $role_ids['profesional'] ?? $userId, 'profesional');
}

$out['pending_messages'] = $totalPending;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
