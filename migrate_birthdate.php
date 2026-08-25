<?php
// migrate_birthdate.php
// Ejecutar una sola vez para inicializar la base de datos
require_once __DIR__ . '/public_html/backend/conexion.php';

$sql = "
CREATE TABLE IF NOT EXISTS profesionales_datos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    fecha_nacimiento DATE,
    UNIQUE KEY (profesional_id),
    FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
);
";

if ($conn->query($sql)) {
    echo "Tabla profesionales_datos creada o ya existente.<br>";
} else {
    echo "Error creando tabla: " . $conn->error . "<br>";
}

$sqlPopulate = "
INSERT IGNORE INTO profesionales_datos (profesional_id, fecha_nacimiento)
SELECT id, '2020-01-01' FROM profesionales;
";

if ($conn->query($sqlPopulate)) {
    echo "Registros inicializados con 01-01-2020 correctamente.<br>";
} else {
    echo "Error inicializando registros: " . $conn->error . "<br>";
}

echo "Migración completada.";
?>
