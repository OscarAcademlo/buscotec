<?php
// backend/get_profesionales_por_categoria.php — versión estable

declare(strict_types=1);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false, 'profesionales' => []];

/* ============================
   Validación de parámetros
============================ */
if (
    !isset($_GET['id']) ||
    !isset($_GET['lat']) ||
    !isset($_GET['lng'])
) {
    echo json_encode($response);
    exit;
}

$id_categoria = (int) $_GET['id'];
$latUser = (float) $_GET['lat'];
$lngUser = (float) $_GET['lng'];
$radio = 5; // km de búsqueda

/* ============================
   Consulta con distancia
============================ */
$sql = "
SELECT 
    p.id,
    p.nombre,
    p.apellido,
    p.whatsapp,
    p.direccion,
    p.experiencia,
    p.descripcion,
    p.foto_profesional,
    p.rating,
    p.estado_servicio,

    /* Ubicación final (de ubicaciones_usuarios si existe) */
    COALESCE(up.lat, p.lat) AS lat,
    COALESCE(up.lng, p.lng) AS lng,

    /* Distancia real */
    (
        6371 * acos(
            cos(radians(?)) *
            cos(radians(COALESCE(up.lat, p.lat))) *
            cos(radians(COALESCE(up.lng, p.lng)) - radians(?)) +
            sin(radians(?)) *
            sin(radians(COALESCE(up.lat, p.lat)))
        )
    ) AS distancia

FROM profesionales p
LEFT JOIN profesional_categorias pc 
    ON pc.profesional_id = p.id
LEFT JOIN ubicaciones_usuarios up 
    ON up.user_id = p.id AND up.rol = 'profesional'

WHERE (p.categoria_id = ? OR pc.categoria_id = ?)
  AND p.verificado = 1
  AND (p.lat IS NOT NULL OR up.lat IS NOT NULL)

HAVING distancia <= ?
ORDER BY distancia ASC
LIMIT 10
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "dddiid",
    $latUser,
    $lngUser,
    $latUser,
    $id_categoria,
    $id_categoria,
    $radio
);
$stmt->execute();
$result = $stmt->get_result();

/* ============================
   Formatear respuesta
============================ */
$lista = [];

while ($row = $result->fetch_assoc()) {

    $foto = $row['foto_profesional'] ? trim($row['foto_profesional']) : '';
    if (!$foto) {
        $foto = 'img/placeholder-pro.jpg';
    } else {
        // Si ya contiene la ruta completa, la dejamos. Si no, la armamos.
        if (strpos($foto, 'img/') === false) {
            $foto = 'img/profesionales/' . $foto;
        }
    }

    $lista[] = [
        'id' => (int) $row['id'],
        'nombre' => trim($row['nombre'] . ' ' . $row['apellido']),
        'whatsapp' => $row['whatsapp'],
        'direccion' => $row['direccion'],
        'experiencia' => (int) $row['experiencia'],
        'descripcion' => $row['descripcion'],

        'foto' => $foto,
        'rating' => $row['rating'] ? (float) $row['rating'] : 0,

        'lat' => (float) $row['lat'],
        'lng' => (float) $row['lng'],
        'distancia' => round((float) $row['distancia'], 2),

        'estado_servicio' => (int) $row['estado_servicio']
    ];
}

$response['ok'] = true;
$response['profesionales'] = $lista;

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();
