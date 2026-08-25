<?php
// backend/verificar_calificacion.php
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$profesional_id = intval($_GET['profesional_id'] ?? 0);
$usuario_id     = intval($_GET['usuario_id'] ?? 0);
$caso_id        = intval($_GET['caso_id'] ?? 0);

if ($caso_id <= 0 && ($profesional_id <= 0 || $usuario_id <= 0)) {
  echo json_encode(['ok' => false, 'error' => 'Datos insuficientes']);
  exit;
}

// Normalizar caso_id para que siempre sea el ID real del caso
if ($caso_id > 0) {
    try {
        $stF = $conn->prepare("SELECT id FROM casos WHERE id=? OR mensaje_id=? LIMIT 1");
        $stF->bind_param('ii', $caso_id, $caso_id);
        $stF->execute();
        $rF = $stF->get_result()->fetch_assoc();
        if ($rF) {
          $caso_id = intval($rF['id']);
        }
    } catch (Exception $e) {}
}

try {
  if ($caso_id > 0) {
    // Verificación por Caso (más precisa)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM calificaciones WHERE caso_id=?");
    $stmt->bind_param('i', $caso_id);
  } else {
    // Verificación por Profesional/Usuario (genérica)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM calificaciones WHERE usuario_id=? AND profesional_id=?");
    $stmt->bind_param('ii', $usuario_id, $profesional_id);
  }
  
  $stmt->execute();
  $stmt->bind_result($existe);
  $stmt->fetch();
  $stmt->close();

  echo json_encode(['ok' => true, 'ya_calificado' => ($existe > 0)]);
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
