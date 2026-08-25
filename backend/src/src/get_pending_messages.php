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

// 2) Contar no leídos con filtros (INTENTOS: mensajes.a_id → mensajes.destinatario_id → mensajes_internos.a_id)
if (!($conn instanceof mysqli)) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper para ejecutar un conteo seguro
$tryCount = function (mysqli $db, string $sql, int $uid): ?int {
    $stmt = @$db->prepare($sql);
    if (!$stmt)
        return null;
    $stmt->bind_param('i', $uid);
    if (!@$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $res = $stmt->get_result();
    $n = 0;
    if ($res && ($row = $res->fetch_assoc())) {
        $n = (int) ($row['n'] ?? 0);
    }
    $stmt->close();
    return $n;
};

$count = null;

// Intento 1: tabla 'mensajes' con 'a_id'
$sql1 = "SELECT COUNT(*) AS n
         FROM mensajes
         WHERE a_id = ?
           AND COALESCE(eliminado,0) = 0
           AND COALESCE(leido,0) = 0
           AND contenido IS NOT NULL";
$count = $tryCount($conn, $sql1, $userId);

// Intento 2: tabla 'mensajes' con 'destinatario_id'
if ($count === null) {
    $sql2 = "SELECT COUNT(*) AS n
             FROM mensajes
             WHERE destinatario_id = ?
               AND COALESCE(eliminado,0) = 0
               AND COALESCE(leido,0) = 0
               AND contenido IS NOT NULL";
    $count = $tryCount($conn, $sql2, $userId);
}

// Intento 3: tabla alternativa 'mensajes_internos' con 'a_id'
if ($count === null) {
    $sql3 = "SELECT COUNT(*) AS n
             FROM mensajes_internos
             WHERE a_id = ?
               AND COALESCE(eliminado,0) = 0
               AND COALESCE(leido,0) = 0
               AND contenido IS NOT NULL";
    $count = $tryCount($conn, $sql3, $userId);
}

$out['pending_messages'] = (int) max(0, $count ?? 0);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
