<?php
// ============================================================
// backend/conexion.php — versión estable 2025 (Hostinger)
// ============================================================

// --- CONFIGURACIÓN DE ENTORNOS (DESARROLLO / PRODUCCIÓN) ---
$isLocal = (
    ($_SERVER['SERVER_NAME'] ?? '') === 'localhost' ||
    ($_SERVER['HTTP_HOST'] ?? '') === 'localhost:8080' ||
    ($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1'
);

if ($isLocal) {
    // Configuración para XAMPP / Local
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $database = 'buscotec'; // <-- CAMBIA ESTO si tu base de datos local se llama distinto
} else {
    // Configuración para Hostinger (Producción)
    $host = 'localhost';
    $user = 'u237313556_buscotec';
    $password = 'Pirulo1771??';
    $database = 'u237313556_buscotec';
}

// 🕒 Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

mysqli_report(MYSQLI_REPORT_OFF); // Evita warnings duplicados
$conn = @new mysqli($host, $user, $password, $database);

// 🧩 Si falla la conexión
if ($conn->connect_errno) {
    error_log('[DB_CONNECT] Error (' . $conn->connect_errno . '): ' . $conn->connect_error);
    $conn = null;
    return; // Detiene la ejecución sin romper el HTML
}

// ✅ Configurar zona horaria solo si la conexión es válida
$conn->query("SET time_zone = '-03:00'");

// 🔒 Charset y collation correctos (importante para ñ/acentos)
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES 'utf8mb4'");
$conn->query("SET CHARACTER SET utf8mb4");
