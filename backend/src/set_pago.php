<?php
// ============================================================
// backend/set_pago.php — versión 2025 con historial de pagos
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_set_pago.log');

function out(array $a) {
  while (ob_get_level()) ob_end_clean();
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}

// --- Validar conexión ---
if (!($conn instanceof mysqli)) out(['ok' => false, 'error' => 'Sin conexión a la base de datos']);

// --- Validar datos recibidos ---
$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;           // ID del profesional
$pago = isset($_POST['pago']) ? (float)$_POST['pago'] : 0.0;   // Monto del pago

if ($id <= 0) out(['ok' => false, 'error' => 'ID inválido']);
if ($pago <= 0) out(['ok' => false, 'error' => 'Monto inválido']);

// --- Registrar pago ---
$stmt = $conn->prepare("
  INSERT INTO pagos_profesionales (profesional_id, monto, registrado_por)
  VALUES (?, ?, 'admin')
");
if (!$stmt) out(['ok' => false, 'error' => 'Error preparando SQL: ' . $conn->error]);

$stmt->bind_param('id', $id, $pago);
if (!$stmt->execute()) out(['ok' => false, 'error' => 'Error ejecutando SQL: ' . $stmt->error]);
$stmt->close();

// --- Confirmar ---
out(['ok' => true, 'id' => $id, 'monto' => $pago]);
