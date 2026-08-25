<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "--- DIAGNÓSTICO DE BASE DE DATOS Y PHP ---\n";

require_once __DIR__ . '/conexion.php';

if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.\n");
}

echo "Conexión a la base de datos: OK\n";

// 1. Verificar si la función get_result existe en mysqli_stmt
if (!method_exists('mysqli_stmt', 'get_result')) {
    echo "¡ERROR CRÍTICO! El servidor NO soporta get_result(). El driver mysqlnd no está habilitado en Hostinger.\n";
} else {
    echo "Soporte de get_result(): OK\n";
}

// 2. Probar la consulta directa de periodos
try {
    $resP = $conn->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS per FROM sorteo_participantes ORDER BY per DESC");
    if (!$resP) {
        echo "Error en SELECT DISTINCT: " . $conn->error . "\n";
    } else {
        echo "SELECT DISTINCT periodos: OK (Encontrados: " . $resP->num_rows . ")\n";
        while($row = $resP->fetch_assoc()) {
            echo " - Período: " . ($row['per'] ?? 'NULL') . "\n";
        }
    }
} catch (Throwable $e) {
    echo "Excepción en SELECT DISTINCT: " . $e->getMessage() . "\n";
}

// 3. Probar la consulta directa (nueva lógica del sistema)
try {
    $periodo = date('Y-m');
    $periodo_escaped = $conn->real_escape_string($periodo);
    $sql = "SELECT id, email, telefono, nombre, apellido, referido_nombre, referido_instagram, numero_sorteo, email_enviado, created_at 
            FROM sorteo_participantes 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = '$periodo_escaped' 
            ORDER BY email, created_at ASC";
            
    $res = $conn->query($sql);
    if (!$res) {
        echo "Error en SELECT Directo: " . $conn->error . "\n";
    } else {
        echo "SELECT Directo: OK (Filas: " . $res->num_rows . ")\n";
        $contador = 0;
        while ($row = $res->fetch_assoc()) {
            if ($contador < 3) {
                echo " - Fila " . ($contador + 1) . ": " . $row['nombre'] . " " . $row['apellido'] . " (" . $row['numero_sorteo'] . ")\n";
            }
            $contador++;
        }
        if ($contador > 3) {
            echo " ... y " . ($contador - 3) . " filas más.\n";
        }
    }
} catch (Throwable $e) {
    echo "Excepción en SELECT Directo: " . $e->getMessage() . "\n";
}
