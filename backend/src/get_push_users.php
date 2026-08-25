<?php
// =========================================================
// Backend: Obtiene la lista de usuarios (Profesionales y Clientes)
// con un webpushr_id válido.
// =========================================================

// 1. Establece el encabezado JSON antes de cualquier otra salida.
header('Content-Type: application/json');

// 2. CORRECCIÓN CRÍTICA DE RUTA (Asume conexion.php está en la carpeta raíz)
// Si hay errores de JSON, el 99% de las veces es por algo que se imprime antes de aquí.
require_once '../conexion.php'; 

// Función de utilidad para respuesta de error
function sendJsonError($message, $httpCode = 500) {
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// Verifica que la conexión PDO exista.
if (!isset($pdo)) {
    sendJsonError("Fallo en la conexión. La variable \$pdo no está definida en conexion.php.");
}

$users = [];

try {
    // 1. Consulta para PROFESIONALES
    $sql_prof = "SELECT nombre, apellido, webpushr_id, 'profesional' AS tipo  
                 FROM profesionales  
                 WHERE webpushr_id IS NOT NULL AND webpushr_id != ''";
    $stmt_prof = $pdo->query($sql_prof);

    while ($row = $stmt_prof->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['webpushr_id'])) {
            $users[] = [
                'id' => $row['webpushr_id'],
                // Formato: Nombre Apellido (Profesional)
                'nombre' => $row['nombre'] . ' ' . $row['apellido'] . ' (Profesional)'
            ];
        }
    }

    // 2. Consulta para CLIENTES/USUARIOS
    $sql_cli = "SELECT nombre, apellido, webpushr_id, 'cliente' AS tipo
                FROM usuarios  
                WHERE webpushr_id IS NOT NULL AND webpushr_id != ''";
    $stmt_cli = $pdo->query($sql_cli);

    while ($row = $stmt_cli->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['webpushr_id'])) {
            $users[] = [
                'id' => $row['webpushr_id'],
                // Formato: Nombre Apellido (Cliente)
                'nombre' => $row['nombre'] . ' ' . $row['apellido'] . ' (Cliente)'
            ];
        }
    }
    
    // Opcional: ordenar la lista final por nombre para facilitar la búsqueda
    usort($users, function($a, $b) {
        return strcmp($a['nombre'], $b['nombre']);
    });

    // ÉXITO: Envía la respuesta JSON
    echo json_encode(['success' => true, 'users' => $users]);
    
} catch (PDOException $e) {
    // Captura errores de base de datos
    sendJsonError('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    // Captura cualquier otro error de ejecución
    sendJsonError('Fallo interno del servidor: ' . $e->getMessage(), 500);
}
// RECOMENDACIÓN CRÍTICA: NO USAR LA ETIQUETA DE CIERRE PHP (?>)
// Y NO DEJAR ESPACIOS O SALTOS DE LÍNEA DESPUÉS DEL ÚLTIMO CARÁCTER.