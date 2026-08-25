<?php
// backend/get_profesionales_cercanos.php
declare(strict_types=1);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false, 'profesionales' => []];

if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
    echo json_encode($response);
    exit;
}

$latUser = (float) $_GET['lat'];
$lngUser = (float) $_GET['lng'];
$radio = 30; // km de búsqueda

/* ============================
   Consulta RELAJADA (Traer todos los verificados, con o sin ubicación)
============================ */
$sql = "
SELECT 
    p.id,
    p.nombre,
    p.apellido,
    p.whatsapp,
    p.direccion,
    p.foto_profesional,
    p.estado_servicio,
    c.nombre AS categoria_nombre,

    /* Ubicación final (si tiene) */
    COALESCE(up.lat, p.lat) AS lat,
    COALESCE(up.lng, p.lng) AS lng,

    /* Distancia real (si se puede calcular, sino NULL) */
    (
        CASE WHEN (p.lat IS NOT NULL OR up.lat IS NOT NULL) THEN
            (
                6371 * acos(
                    cos(radians(?)) *
                    cos(radians(COALESCE(up.lat, p.lat))) *
                    cos(radians(COALESCE(up.lng, p.lng)) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(COALESCE(up.lat, p.lat)))
                )
            )
        ELSE 9999 END
    ) AS distancia

FROM profesionales p
LEFT JOIN categorias c ON c.id = p.categoria_id
LEFT JOIN ubicaciones_usuarios up 
    ON up.user_id = p.id AND up.rol = 'profesional'

WHERE p.verificado = 1
/* AND (p.lat IS NOT NULL OR up.lat IS NOT NULL)  <-- FILTRO ELIMINADO */

/* HAVING distancia <= ? <-- FILTRO ELIMINADO */
ORDER BY distancia ASC, p.id DESC
LIMIT 50
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ddd", $latUser, $lngUser, $latUser); // Solo 3 parámetros ahora (lat, lng, lat)
$stmt->execute();
$result = $stmt->get_result();

$lista = [];
while ($row = $result->fetch_assoc()) {
    $foto = $row['foto_profesional'] ? trim($row['foto_profesional']) : '';
    if (!$foto) {
        $foto = 'img/logo_web.png';
    } else {
        if (strpos($foto, 'img/') === false && strpos($foto, 'uploads_perfiles/') === false) {
            $foto = 'img/profesionales/' . $foto;
        }
        $fullPath = __DIR__ . '/../' . $foto;
        if (!file_exists($fullPath)) {
            $foto = 'img/logo_web.png';
        }
    }

    $lista[] = [
        'id' => (int) $row['id'],
        'nombre' => trim((string)$row['nombre']),
        'apellido' => trim((string)$row['apellido']),
        'categoria' => $row['categoria_nombre'] ?? 'Profesional',
        'foto' => $foto,
        'lat' => (float) $row['lat'],
        'lng' => (float) $row['lng'],
        'dist_km' => round((float) $row['distancia'], 2),
        'distancia' => round((float) $row['distancia'], 2),
        'disponible' => 1 // FORZADO SIEMPRE ACTIVO COMO PIDIÓ EL USUARIO
    ];
}

$response['ok'] = true;
$response['profesionales'] = $lista;

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
