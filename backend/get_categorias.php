<?php
// backend/get_categorias.php
declare(strict_types=1);

// 🚫 Evita cualquier salida previa (espacios, BOM, warnings)
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Configuración de errores: loguea pero no muestra
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

// --- Conexión ---
require_once __DIR__ . '/conexion.php';

$response = ['ok' => false, 'items' => []];

try {
    // ✅ Validar conexión
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $conn->set_charset('utf8mb4');

    // Asegurar que exista la columna suspendido en profesionales para evitar errores
    @$conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");

    // ✅ Consultar categorías ordenadas por cantidad de oferentes activos y verificados
    $sql = "SELECT c.id, c.nombre,
            (
                SELECT COUNT(DISTINCT p.id) 
                FROM profesionales p
                LEFT JOIN profesional_categorias pc ON pc.profesional_id = p.id
                WHERE (p.categoria_id = c.id OR pc.categoria_id = c.id)
                  AND p.verificado = 1 
                  AND (p.suspendido = 0 OR p.suspendido IS NULL)
            ) AS total_oferentes
            FROM categorias c
            ORDER BY total_oferentes DESC, c.nombre ASC";
    $res = $conn->query($sql);

    if ($res === false) {
        throw new Exception('Error en la consulta SQL: ' . $conn->error);
    }

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $nombre = trim($row['nombre']);
            // Reemplazo flexible para cualquier variante de "Artista / Pintor"
            if (stripos($nombre, 'Artista') !== false && stripos($nombre, 'Pintor') !== false) {
                $nombre = 'Viandas a domicilio';
            }
            $response['items'][] = [
                'id' => (int) $row['id'],
                'nombre' => $nombre,
                'total_oferentes' => (int)($row['total_oferentes'] ?? 0)
            ];
        }
        $response['ok'] = true;
    } else {
        $response['ok'] = true;
        $response['items'] = [];
        $response['message'] = 'No hay categorías disponibles.';
    }

    $res->free_result();
} catch (Throwable $e) {
    $response['ok'] = false;
    $response['error'] = $e->getMessage();
    error_log('[GET_CATEGORIAS] ' . $e->getMessage());
}

// ✅ Cerrar conexión
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// ✅ Limpiar cualquier salida previa (espacios, BOM)
ob_end_clean();

// ✅ Enviar JSON puro
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
