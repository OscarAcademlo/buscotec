<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/session_boot.php";

$response = ['ok' => false];

$profId = $_SESSION['role_ids']['profesional'] ?? null;

// Fallback: Si no hay ID profesional en sesión, buscar por email
if (!$profId && !empty($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $stmtEmail = $conn->prepare("SELECT id FROM profesionales WHERE email = ? LIMIT 1");
    $stmtEmail->bind_param("s", $email);
    $stmtEmail->execute();
    $resEmail = $stmtEmail->get_result();
    if ($rowEmail = $resEmail->fetch_assoc()) {
        $profId = $rowEmail['id'];
        // Guardarlo en sesión para la próxima
        $_SESSION['role_ids']['profesional'] = $profId;
    }
}

if (!$profId) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo identificar al profesional.']);
    exit;
}

// Verificar estado actual
$sql = "SELECT estado_servicio FROM profesionales WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $profId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $nuevoEstado = $row['estado_servicio'] ? 0 : 1;

    $update = $conn->prepare("UPDATE profesionales SET estado_servicio = ? WHERE id = ?");
    $update->bind_param('ii', $nuevoEstado, $profId);
    $update->execute();

    $response['ok'] = true;
    $response['estado_servicio'] = $nuevoEstado;
} else {
    $response['error'] = 'Profesional no encontrado';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>