<?php
// backend/listar_casos_app.php
// Endpoint dedicado para la App Flutter — autenticación sin sesión PHP
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
// CORS para la app web Flutter
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['http://localhost:3000', 'https://buscotec.click', 'https://oscarsoft.click'], true)) {
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

if (!$uid || $role !== 'profesional') {
    outApp(['ok' => false, 'error' => 'ID de profesional requerido.']);
}

$profesional_id = $uid;

// 2. Obtener el valor del caso
$valorCaso = 0.0;
$resValor = $conn->query("SELECT valor FROM ajustes WHERE clave = 'valor_caso' LIMIT 1");
if ($resValor && $resValor->num_rows > 0) {
    $valorCaso = (float) $resValor->fetch_assoc()['valor'];
}

// 3. Obtener casos aceptados
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
    outApp(['ok' => false, 'error' => 'Error de base de datos']);
}

$stmt->bind_param('i', $profesional_id);
$stmt->execute();
$res = $stmt->get_result();

$casos = [];
$totalPendiente = 0.0;

while ($row = $res->fetch_assoc()) {
    $importe = (float) $valorCaso;
    $pagado = (int) $row['pagado'];

    $row['importe'] = number_format($importe, 2, '.', '');
    $row['pagado'] = $pagado;

    if ($pagado === 0) {
        $totalPendiente += $importe;
    }

    $casos[] = $row;
}
$stmt->close();

outApp([
    'ok' => true,
    'moneda' => 'ARS',
    'total' => number_format($totalPendiente, 2, '.', ''),
    'casos' => $casos
]);
