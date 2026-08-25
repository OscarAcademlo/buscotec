<?php
// backend/reparar_tablas.php
require_once 'conexion.php';
header('Content-Type: text/plain; charset=utf-8');

echo "--- INICIANDO REPARACIÓN DE TABLAS ---\n\n";

try {
    // 1. Asegurar columnas en profesionales
    echo "1. Verificando columnas lat/lng en tabla 'profesionales'...\n";
    $conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS lat DECIMAL(10,7) NULL");
    $conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS lng DECIMAL(10,7) NULL");
    echo "   OK.\n\n";

    // 2. Asegurar tabla de ubicaciones
    echo "2. Verificando tabla 'ubicaciones_usuarios'...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS ubicaciones_usuarios (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          rol ENUM('usuario','profesional') NOT NULL,
          lat DECIMAL(10,7) NOT NULL,
          lng DECIMAL(10,7) NOT NULL,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_user_rol (user_id, rol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "   OK.\n\n";

    // 3. Limpiar posibles registros huérfanos o inválidos
    echo "3. Optimizando índices...\n";
    $conn->query("ANALYZE TABLE profesionales, ubicaciones_usuarios");
    echo "   OK.\n\n";

    // 4. Asegurar tabla de modales programados (Anuncios)
    echo "4. Verificando tabla 'modales_programados'...\n";
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
    echo "   OK.\n\n";

    echo "✅ TODO REPARADO. El botón 'Estoy aquí' y el sistema de Modales Programados están listos.\n";
    echo "Por favor, cerrá sesión y volvé a entrar para asegurar que el sistema te identifique bien.\n";

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
