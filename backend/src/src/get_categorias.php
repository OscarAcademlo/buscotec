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

    // ✅ Consultar categorías
    $sql = "SELECT id, nombre FROM categorias ORDER BY nombre ASC";
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
                'nombre' => $nombre
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
