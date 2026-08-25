<?php
// backend/listar_mensajes.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/jwt_helper.php'; // opcional (por si llega Bearer)

function out(array $arr, int $code = 200)
{
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

/* 1) Resolver user_id por sesión o JWT */
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id)
  $user_id = (int) ($_SESSION['id'] ?? 0);

if (!$user_id && function_exists('getallheaders')) {
  $headers = getallheaders();
  if (!empty($headers['Authorization'])) {
    [$type, $jwt] = explode(' ', $headers['Authorization'], 2);
    if ($type === 'Bearer' && $jwt) {
      $secretKey = 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026';
      $payload = jwt_decode($jwt, $secretKey);
      if ($payload) {
        $user_id = (int) ($payload['uid'] ?? $payload['user_id'] ?? 0);
      }
    }
  }
}
if (!$user_id)
  out(['ok' => false, 'error' => 'No autorizado'], 401);

/* 2) Inputs */
$caso_id = (int) ($_GET['caso_id'] ?? 0);
if ($caso_id <= 0)
  out(['ok' => false, 'error' => 'ID de caso inválido'], 400);

$offset = max(0, (int) ($_GET['offset'] ?? 0));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 50))); // tope 100

/* 3) Verificar acceso al caso */
if (!($conn instanceof mysqli))
  out(['ok' => false, 'error' => 'Sin conexión DB'], 500);

$sql = "SELECT id FROM casos WHERE id = ? AND (user_id = ? OR profesional_id = ?) LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $caso_id, $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || !$res->num_rows)
  out(['ok' => false, 'error' => 'No tienes acceso a este caso'], 403);
$stmt->close();

/* 4) Listar mensajes LIMPIOS, adjuntos vía subquery JSON */
$sql = "
  SELECT
    m.id,
    m.caso_id,
    m.de_id,
    m.a_id,
    COALESCE(m.mensaje, m.contenido) AS contenido,
    CASE WHEN m.de_id = 0 THEN 'Sistema' ELSE '' END AS de_nombre_sistema,
    DATE_FORMAT(m.fecha_envio, '%Y-%m-%d %H:%i:%s') AS fecha_envio,
    COALESCE(m.leido, 0)     AS leido,
    COALESCE(m.eliminado, 0) AS eliminado,
    (
      SELECT JSON_ARRAYAGG(
               JSON_OBJECT('id', a.id, 'ruta', a.ruta, 'mime', a.mime, 'peso', a.peso)
             )
      FROM mensaje_adjuntos a
      WHERE a.mensaje_id = m.id
    ) AS adjuntos
  FROM mensajes m
  WHERE m.caso_id = ?
    AND COALESCE(m.eliminado, 0) = 0
    AND (m.contenido IS NOT NULL OR m.mensaje IS NOT NULL)
  ORDER BY m.fecha_envio ASC, m.id ASC
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $caso_id, $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();

$mensajes = [];
while ($row = $res->fetch_assoc()) {
  $row['adjuntos'] = $row['adjuntos'] ? json_decode($row['adjuntos'], true) : [];
  $mensajes[] = $row;
}

/* 5) Total para paginación (opcional) */
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM mensajes m
  WHERE m.caso_id = ?
    AND COALESCE(m.eliminado, 0) = 0
    AND m.contenido IS NOT NULL
";
$stmt = $conn->prepare($sqlCount);
$stmt->bind_param("i", $caso_id);
$stmt->execute();
$resCount = $stmt->get_result();
$total = ($resCount && ($r = $resCount->fetch_assoc())) ? (int) $r['total'] : count($mensajes);

out(['ok' => true, 'mensajes' => $mensajes, 'total' => $total, 'offset' => $offset, 'limit' => $limit]);
