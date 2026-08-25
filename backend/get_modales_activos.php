<?php
// backend/get_modales_activos.php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false, 'modal' => null];

if (!isset($conn) || !$conn) {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Asegurar existencia de la tabla
    $conn->query("
        CREATE TABLE IF NOT EXISTS modales_programados (
          id INT AUTO_INCREMENT PRIMARY KEY,
          titulo VARCHAR(255) NOT NULL,
          texto TEXT NOT NULL,
          imagen_principal VARCHAR(500) NULL,
          sub_titulo_1 VARCHAR(255) NULL,
          sub_texto_1 TEXT NULL,
          sub_imagen_1 VARCHAR(500) NULL,
          sub_titulo_2 VARCHAR(255) NULL,
          sub_texto_2 TEXT NULL,
          sub_imagen_2 VARCHAR(500) NULL,
          fecha_inicio DATETIME NOT NULL,
          fecha_fin DATETIME NOT NULL,
          activo TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $sql = "
        SELECT * FROM modales_programados
        WHERE activo = 1
          AND NOW() BETWEEN fecha_inicio AND fecha_fin
        ORDER BY id DESC
        LIMIT 1
    ";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $response = [
            'ok' => true,
            'modal' => [
                'id' => (int)$row['id'],
                'titulo' => $row['titulo'],
                'texto' => $row['texto'],
                'imagen_principal' => $row['imagen_principal'],
                'sub_titulo_1' => $row['sub_titulo_1'],
                'sub_texto_1' => $row['sub_texto_1'],
                'sub_imagen_1' => $row['sub_imagen_1'],
                'sub_titulo_2' => $row['sub_titulo_2'],
                'sub_texto_2' => $row['sub_texto_2'],
                'sub_imagen_2' => $row['sub_imagen_2'],
                'fecha_inicio' => $row['fecha_inicio'],
                'fecha_fin' => $row['fecha_fin']
            ]
        ];
    } else {
        $response = ['ok' => true, 'modal' => null];
    }
} catch (Throwable $e) {
    $response = ['ok' => false, 'error' => $e->getMessage(), 'modal' => null];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
