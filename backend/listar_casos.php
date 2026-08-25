<?php
declare(strict_types=1);

/* ======================================================
   BOOT ÚNICO DE SESIÓN (EL MISMO DEL LOGIN)
====================================================== */
require_once __DIR__ . '/_api_boot.php';

/* ======================================================
   HEADERS (OBLIGATORIOS PARA SESIÓN + FETCH)
====================================================== */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://buscotec.click');
header('Access-Control-Allow-Credentials: true');

/* ======================================================
   AUTH REAL (SOPORTA DOBLE ROL)
====================================================== */
if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Sesión no válida'
    ]);
    exit;
}

if (
    ($_SESSION['role'] ?? '') !== 'profesional'
    && empty($_SESSION['is_profesional'])
) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

$profesional_id = (int) $_SESSION['id'];

/* ======================================================
   DB
====================================================== */
require_once __DIR__ . '/conexion.php';

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'DB FAIL'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

/* ======================================================
   VALOR DEL CASO (ARS)
====================================================== */
$valorCaso = 0.0;

$resValor = $conn->query(
    "SELECT valor FROM ajustes WHERE clave = 'valor_caso' LIMIT 1"
);

if ($resValor && $resValor->num_rows > 0) {
    $valorCaso = (float) $resValor->fetch_assoc()['valor'];
}

/* ======================================================
   CASOS ACEPTADOS DEL PROFESIONAL
====================================================== */
$sql = "
SELECT
    c.id,
    DATE(c.created_at) AS fecha,
    TIME(c.created_at) AS hora,
    u.nombre,
    u.apellido,
    u.whatsapp,
    COALESCE(c.pagado, 0) AS pagado
FROM casos c
INNER JOIN usuarios u ON u.id = c.solicitante_id
WHERE c.receptor_tipo = 'profesional'
  AND c.receptor_id = ?
  AND c.estado = 'aceptado'
ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'SQL PREPARE ERROR'
    ]);
    exit;
}

$stmt->bind_param('i', $profesional_id);
$stmt->execute();
$res = $stmt->get_result();

/* ======================================================
   ARMAR RESPUESTA
====================================================== */
$casos = [];
$totalPendiente = 0.0;

while ($row = $res->fetch_assoc()) {

    $importe = (float) $valorCaso;
    $pagado  = (int) $row['pagado'];

    $row['importe'] = number_format($importe, 2, '.', '');
    $row['pagado']  = $pagado;

    if ($pagado === 0) {
        $totalPendiente += $importe;
    }

    $casos[] = $row;
}

$stmt->close();

/* ======================================================
   RESPUESTA FINAL
====================================================== */
echo json_encode([
    'ok'     => true,
    'moneda' => 'ARS',
    'total'  => number_format($totalPendiente, 2, '.', ''),
    'casos'  => $casos
], JSON_UNESCAPED_UNICODE);
