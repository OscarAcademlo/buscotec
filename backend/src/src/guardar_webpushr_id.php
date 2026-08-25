<?php
declare(strict_types=1);
require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['id'] ?? 0;
$role   = $_SESSION['role'] ?? '';
$sid    = trim($_POST['subscriber_id'] ?? '');

if (!$userId || !$sid) {
  echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
  exit;
}

$tabla = ($role === 'profesional') ? 'profesionales' : 'usuarios';

try {
  $q = $conn->prepare("UPDATE $tabla SET webpushr_id=? WHERE id=? LIMIT 1");
  $q->bind_param('si', $sid, $userId);
  $q->execute();
  $q->close();
  echo json_encode(['ok' => true, 'tabla' => $tabla, 'user' => $userId]);
} catch (Throwable $e) {
  error_log('❌ Error guardar_webpushr_id: ' . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
