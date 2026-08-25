<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: text/plain; charset=utf-8');

$conn->set_charset('utf8mb4');

// --- Generar datos de prueba ---
$id_global = md5(uniqid(rand(), true));
$nombre = 'Test';
$apellido = 'Insert';
$email = 'test_insert_' . rand(1000,9999) . '@example.com';
$whatsapp = '0000000000';
$acepta_terminos = 1;
$direccion = 'Dirección Prueba';
$barrio = 'Centro';
$ciudad = 'Bariloche';
$provincia = 'Río Negro';
$categoria_id = 1;
$experiencia = 5;
$foto_profesional = '';
$foto_matricula = '';
$descripcion = 'Prueba de inserción directa';
$rating = 0.0;
$password_hash = password_hash('12345678', PASSWORD_DEFAULT);
$email_verificado = 0;
$token_verificacion = bin2hex(random_bytes(16));
$created_at = date('Y-m-d H:i:s');
$verificado = 0;
$fecha_registro = date('Y-m-d H:i:s');
$lat = null;
$lng = null;
$estado_servicio = 0;
$onesignal_id = null;
$webpushr_id = null;

// --- SQL exacto según tu tabla profesionales ---
$sql = "INSERT INTO profesionales (
  id_global, nombre, apellido, email, whatsapp, acepta_terminos,
  direccion, barrio, ciudad, provincia, categoria_id, experiencia,
  foto_profesional, foto_matricula, descripcion, rating, password,
  email_verificado, token_verificacion, created_at, verificado,
  fecha_registro, lat, lng, estado_servicio, onesignal_id, webpushr_id
) VALUES (
  ?,?,?,?,?,?,
  ?,?,?,?, ?,?,
  ?,?,?,?,?,
  ?,?,?,?,?,
  ?,?,?,?,?
)";

// --- Preparar sentencia ---
$stmt = $conn->prepare($sql);
$stmt->bind_param(
  'ssssisssssisssssdsssisddiss',
  $id_global, $nombre, $apellido, $email, $whatsapp,
  $acepta_terminos,
  $direccion, $barrio, $ciudad, $provincia,
  $categoria_id, $experiencia,
  $foto_profesional, $foto_matricula, $descripcion,
  $rating, $password_hash,
  $email_verificado, $token_verificacion, $created_at,
  $verificado, $fecha_registro,
  $lat, $lng, $estado_servicio, $onesignal_id, $webpushr_id
);

// --- Ejecutar y verificar ---
try {
  $stmt->execute();
  echo "✅ Inserción OK\n";
  echo "Email: $email\n";
} catch (Throwable $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
