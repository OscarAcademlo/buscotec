<?php
require_once __DIR__ . "/session_boot.php";
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['ok' => false, 'error' => 'No autenticado']);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$categoria_id = intval($data['categoria_id']);
$user_id = $_SESSION['user_id'];

if (!$categoria_id) {
  echo json_encode(['ok' => false, 'error' => 'ID inválido']);
  exit;
}

$sql = "DELETE FROM categorias_profesional WHERE profesional_id = ? AND categoria_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user_id, $categoria_id);
$ok = $stmt->execute();

echo json_encode(['ok' => $ok]);
?>
