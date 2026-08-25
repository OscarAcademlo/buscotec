<?php
// backend/get_profesional.php — versión final BuscoTec 2025
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Normalizar conexión
$db = null;
if (isset($conn) && $conn instanceof mysqli) $db = $conn;
if (isset($conexion) && $conexion instanceof mysqli) $db = $conexion;
if (!$db) { echo json_encode(['error' => 'db']); exit; }

$db->set_charset('utf8mb4');

// Parámetro
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['error' => 'bad_id']); exit; }

/* =========================================================
   1️⃣ Datos del profesional + promedio + comentario
   ========================================================= */
$sql = "
SELECT 
  p.id,
  p.nombre,
  p.apellido,
  p.email,
  p.whatsapp,
  p.direccion,
  p.barrio,
  p.ciudad,
  p.provincia,
  p.foto_profesional,
  p.foto_matricula,
  p.descripcion,
  p.experiencia,
  p.verificado,
  d.fecha_nacimiento,
  IFNULL(ROUND(AVG(c.puntuacion),1),0) AS promedio,
  COUNT(c.id) AS total_calificaciones
FROM profesionales p
LEFT JOIN profesionales_datos d ON d.profesional_id = p.id
LEFT JOIN calificaciones c ON c.receptor_profesional_id = p.id
WHERE p.id = ?
GROUP BY p.id
";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$pro = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pro) { echo json_encode(['error' => 'not_found']); exit; }

/* =========================================================
   2️⃣ Último comentario y fecha (si existe)
   ========================================================= */
$sqlCom = "
SELECT comentario, puntuacion, fecha_creacion
FROM calificaciones
WHERE receptor_profesional_id = ?
ORDER BY fecha_creacion DESC
LIMIT 1
";
$sc = $db->prepare($sqlCom);
$sc->bind_param('i', $id);
$sc->execute();
$resCom = $sc->get_result()->fetch_assoc();
$sc->close();

if ($resCom) {
  $pro['ultimo_comentario'] = $resCom['comentario'];
  $pro['ultimo_puntaje'] = (float)$resCom['puntuacion'];
  $pro['ultimo_fecha'] = $resCom['fecha_creacion'];
} else {
  $pro['ultimo_comentario'] = null;
  $pro['ultimo_puntaje'] = null;
  $pro['ultimo_fecha'] = null;
}

/* =========================================================
   3️⃣ Categorías
   ========================================================= */
$sql2 = "
SELECT c.id, c.nombre
FROM profesional_categorias pc
JOIN categorias c ON c.id = pc.categoria_id
WHERE pc.profesional_id = ?
ORDER BY c.nombre
";
$st2 = $db->prepare($sql2);
$st2->bind_param('i', $id);
$st2->execute();
$res2 = $st2->get_result();

$cats = [];
while ($r = $res2->fetch_assoc()) {
  $cats[] = ['id' => (int)$r['id'], 'nombre' => $r['nombre']];
}
$st2->close();

$pro['categorias'] = $cats;

// === Respuesta final ===
echo json_encode($pro, JSON_UNESCAPED_UNICODE);
