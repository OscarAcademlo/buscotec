<?php
// backend/crear_caso.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . "/session_boot.php";

function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if (!($conn instanceof mysqli)) out(['ok'=>false,'error'=>'Sin conexión a la base de datos']);

$user_id = $_SESSION['id'] ?? 0; // el cliente logueado
$prof_id = (int)($_POST['profesional_id'] ?? 0);

if (!$user_id || !$prof_id) {
  out(['ok'=>false,'error'=>'Faltan identificadores']);
}

// 1) Crear caso pendiente
$stmt = $conn->prepare("INSERT INTO casos (user_id, profesional_id, estado, created_at) VALUES (?, ?, 'pendiente', NOW())");
$stmt->bind_param('ii', $user_id, $prof_id);
$stmt->execute();
$caso_id = $stmt->insert_id;
$stmt->close();

// 2) Respuesta
out(['ok'=>true,'caso_id'=>$caso_id]);
?>
