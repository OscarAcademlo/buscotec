<?php
declare(strict_types=1);
require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: text/plain; charset=utf-8');

// === Debug directo en pantalla ===
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// === Datos de sesión ===
echo "🧠 SESIÓN ACTUAL:\n";
print_r($_SESSION);

// === Probar conexión ===
if (!($conn instanceof mysqli)) {
  exit("❌ Error: sin conexión a la base de datos.\n");
}

// === ID del profesional actual ===
$id = (int)($_SESSION['id'] ?? 0);
if ($id <= 0) exit("⚠️ No hay sesión activa.\n");

// === Consulta idéntica a la del admin pero filtrada ===
$sql = "
SELECT 
  c.id, c.estado, c.created_at, c.accepted_at, c.cargo_usd,
  u.nombre, u.apellido, u.whatsapp
FROM casos c
LEFT JOIN usuarios u ON u.id = c.solicitante_id
WHERE c.receptor_id = $id
ORDER BY c.created_at DESC
LIMIT 5
";

echo "\n📜 SQL:\n$sql\n\n";

// === Ejecutar ===
$res = $conn->query($sql);
if (!$res) {
  exit("💥 ERROR SQL: " . $conn->error . "\n");
}

echo "✅ RESULTADOS:\n";
while ($row = $res->fetch_assoc()) {
  echo "- Caso {$row['id']} | {$row['nombre']} {$row['apellido']} | {$row['estado']} | {$row['cargo_usd']} USD\n";
}
