<?php
declare(strict_types=1);

/* ======================================================
   BUFFER (una sola vez)
====================================================== */
ob_start();

/* ======================================================
   BOOT DE SESIÓN
====================================================== */
require_once __DIR__ . '/_api_boot.php';

/* ======================================================
   HEADERS
====================================================== */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/* ======================================================
   AUTH
====================================================== */
if (
    empty($_SESSION['id']) ||
    ($_SESSION['role'] ?? '') !== 'profesional'
) {
    ob_clean();
    echo json_encode([
        'ok' => false,
        'error' => 'No autorizado (sesión profesional requerida)'
    ]);
    exit;
}

$profesionalId = (int) $_SESSION['id'];
$mensajeId     = (int) ($_GET['mensaje_id'] ?? 0);

if ($mensajeId <= 0) {
    ob_clean();
    echo json_encode(['ok' => false, 'error' => 'mensaje_id inválido']);
    exit;
}

/* ======================================================
   DB
====================================================== */
require_once __DIR__ . '/conexion.php';
$conn->set_charset('utf8mb4');

/* ======================================================
   MENSAJE
====================================================== */
$sql = "
SELECT remitente_id
FROM mensajes
WHERE id = ?
  AND destinatario_id = ?
  AND destinatario_tipo = 'profesional'
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $mensajeId, $profesionalId);
$stmt->execute();
$res = $stmt->get_result();
$msg = $res->fetch_assoc();

if (!$msg) {
    ob_clean();
    echo json_encode([
        'ok' => false,
        'error' => 'Mensaje inexistente o no autorizado'
    ]);
    exit;
}

$usuarioId = (int) $msg['remitente_id'];

/* ======================================================
   UBICACIÓN
====================================================== */
$sql = "
SELECT lat, lng
FROM ubicaciones_usuarios
WHERE user_id = ?
  AND rol = 'usuario'
ORDER BY updated_at DESC
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$res = $stmt->get_result();
$ubi = $res->fetch_assoc();

if (!$ubi) {
    ob_clean();
    echo json_encode([
        'ok' => false,
        'error' => 'Ubicación no disponible'
    ]);
    exit;
}

/* ======================================================
   RESPUESTA FINAL (UNA SOLA VEZ)
====================================================== */
$lat = (float) $ubi['lat'] + (mt_rand(-15, 15) / 10000);
$lng = (float) $ubi['lng'] + (mt_rand(-15, 15) / 10000);

ob_clean();
echo json_encode([
    'ok'  => true,
    'lat' => round($lat, 6),
    'lng' => round($lng, 6)
]);
exit;
