<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: text/plain; charset=utf-8');

if (!($conn instanceof mysqli)) {
    die("Error de conexión");
}

echo "--- Iniciando actualización de base de datos ---\n";

// 1. Agregar columna cargo_ars si no existe
$res = $conn->query("SHOW COLUMNS FROM casos LIKE 'cargo_ars'");
if ($res->num_rows === 0) {
    echo "Agregando columna cargo_ars a la tabla casos...\n";
    $conn->query("ALTER TABLE casos ADD COLUMN cargo_ars DECIMAL(10,2) DEFAULT NULL AFTER pagado");
} else {
    echo "La columna cargo_ars ya existe.\n";
}

// 2. Crear tabla de trazabilidad
echo "Creando tabla trazabilidad_casos si no existe...\n";
$sqlTraz = "CREATE TABLE IF NOT EXISTS trazabilidad_casos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caso_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    profesional_id INT DEFAULT NULL,
    accion ENUM('aceptado', 'modificado', 'borrado') NOT NULL,
    valor_anterior DECIMAL(10,2) DEFAULT NULL,
    valor_nuevo DECIMAL(10,2) DEFAULT NULL,
    admin_email VARCHAR(255) DEFAULT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sqlTraz)) {
    echo "Tabla trazabilidad_casos lista.\n";
} else {
    echo "Error al crear tabla trazabilidad: " . $conn->error . "\n";
}

// 3. Poblar cargo_ars inicial para casos existentes (opcional pero recomendado)
$resValor = $conn->query("SELECT valor FROM ajustes WHERE clave = 'valor_caso' LIMIT 1");
$valorActual = 1400.00;
if ($resValor && $row = $resValor->fetch_assoc()) {
    $valorActual = (float)$row['valor'];
}

echo "Actualizando casos antiguos con el valor actual ($valorActual)...\n";
$conn->query("UPDATE casos SET cargo_ars = $valorActual WHERE cargo_ars IS NULL AND estado = 'aceptado'");

echo "--- Proceso finalizado ---\n";
