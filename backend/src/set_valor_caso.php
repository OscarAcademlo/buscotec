<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.log');

/* =========================
   VALIDAR CONEXIÓN
========================= */
if (!($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión']);
    exit;
}

$conn->set_charset('utf8mb4');

/* =========================
   VALIDAR VALOR
========================= */
$valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));

if ($valor === '' || !is_numeric($valor) || (float)$valor <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Valor inválido']);
    exit;
}

/* =========================
   GUARDAR VALOR EN PESOS
   👉 CLAVE CORRECTA: valor_caso
========================= */
$stmt = $conn->prepare("
    INSERT INTO ajustes (clave, valor)
    VALUES ('valor_caso', ?)
    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
");

if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Error prepare']);
    exit;
}

$stmt->bind_param('s', $valor);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
