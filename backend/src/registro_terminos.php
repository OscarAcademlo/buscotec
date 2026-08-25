<?php
// ============================================================
// backend/registro_terminos.php — Guarda aceptación de términos
// ============================================================

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php'; // conexión MySQL activa

// --- Leer entrada JSON ---
$input = json_decode(file_get_contents("php://input"), true);

$nombre   = trim($input['nombre']   ?? '');
$apellido = trim($input['apellido'] ?? '');
$email    = trim($input['email']    ?? '');
$tipo     = trim($input['tipo_usuario'] ?? 'usuario');
$acepto   = (int)($input['acepto'] ?? 1);

// --- Validación mínima ---
if ($nombre === '' && $apellido === '' && $email === '') {
  echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
  exit;
}

try {
  // --- Inserción segura ---
  $stmt = $conn->prepare("
    INSERT INTO aceptaciones_terminos (nombre, apellido, email, tipo_usuario, acepto)
    VALUES (?, ?, ?, ?, ?)
  ");
  $stmt->bind_param("ssssi", $nombre, $apellido, $email, $tipo, $acepto);
  $stmt->execute();

  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  error_log('[TERMINOS] ' . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => 'Error interno al registrar aceptación']);
}
