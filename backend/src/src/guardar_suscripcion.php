<?php
/**
 * ✅ Guardar o actualizar Webpushr ID del usuario o profesional
 * Actualiza siempre, incluso si ya existía o si se accede desde otro navegador/dispositivo.
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

// --- Cargar conexión ---
require_once __DIR__ . '/conexion.php';

// Adaptar variable de conexión según tu archivo
if (isset($conn) && !isset($conexion))
    $conexion = $conn;

// Validar conexión
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => '❌ No se encontró conexión MySQL válida']);
    error_log("❌ No se encontró conexión MySQL válida en guardar_suscripcion.php");
    exit;
}

// --- Crear carpeta logs si no existe ---
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir))
    mkdir($logDir, 0755, true);
$logFile = $logDir . '/guardar_suscripcion.log';

// --- Leer y registrar payload ---
$raw = file_get_contents("php://input");
file_put_contents($logFile, date('Y-m-d H:i:s') . " RAW: $raw\n", FILE_APPEND);

$data = json_decode($raw, true);

// Validar parseo JSON
if (!is_array($data)) {
    $msg = "❌ JSON inválido o vacío";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// --- Extraer variables ---
$userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
$rol = trim($data['rol'] ?? '');
$playerId = trim($data['webpushr_id'] ?? '');

// Validar datos mínimos
if ($userId <= 0 || empty($rol) || empty($playerId)) {
    $msg = "❌ Faltan datos: user_id=$userId, rol=$rol, webpushr_id=$playerId";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ❌ Rechazar si el subscriber_id contiene texto de error de WebPushr
if (!is_numeric($playerId) || strlen($playerId) < 3) {
    $msg = "❌ Subscriber ID inválido o es un error de WebPushr: $playerId";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// --- Determinar tabla según rol ---
switch ($rol) {
    case 'usuario':
        $tabla = 'usuarios';
        break;
    case 'profesional':
        $tabla = 'profesionales';
        break;
    default:
        $msg = "❌ Rol inválido ($rol)";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
}

// --- Limpiar duplicados del mismo webpushr_id SOLO si es de OTRO usuario ---
// NO borrar si el webpushr_id pertenece al mismo usuario con doble rol
try {
    // Solo limpiar en la OTRA tabla y solo si NO es el mismo usuario
    // Un usuario puede tener doble rol (usuario + profesional) y usar el mismo navegador
    // Por eso permitimos el mismo webpushr_id en ambas tablas si es el mismo dueño

    // Si estamos guardando para un USUARIO, limpiar otros usuarios (no otros profesionales)
    if ($tabla === 'usuarios') {
        $stmtDel = $conexion->prepare("UPDATE usuarios SET webpushr_id=NULL WHERE webpushr_id=? AND id<>?");
        $stmtDel->bind_param('si', $playerId, $userId);
        $stmtDel->execute();
    }

    // Si estamos guardando para un PROFESIONAL, limpiar otros profesionales (no otros usuarios)
    if ($tabla === 'profesionales') {
        $stmtDel = $conexion->prepare("UPDATE profesionales SET webpushr_id=NULL WHERE webpushr_id=? AND id<>?");
        $stmtDel->bind_param('si', $playerId, $userId);
        $stmtDel->execute();
    }

    // NOTA: Antes se borraba en AMBAS tablas, lo cual causaba que al logearse
    // como usuario se borrara el webpushr_id del profesional (mismo dueño).
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " ⚠️ Error limpiando duplicados: " . $e->getMessage() . "\n", FILE_APPEND);
}

// --- Actualizar o reemplazar el ID actual ---
try {
    $sql = "UPDATE `$tabla` SET webpushr_id=? WHERE id=? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('si', $playerId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        $msg = "🟢 Webpushr ID actualizado ($tabla ID=$userId → $playerId)";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
        echo json_encode(['ok' => true, 'msg' => $msg]);
    } else {
        $msg = "⚠️ Sin cambios en $tabla ID=$userId";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
        echo json_encode(['ok' => true, 'msg' => $msg]);
    }

    $stmt->close();

} catch (Throwable $e) {
    $msg = "❌ Error SQL: " . $e->getMessage();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
?>