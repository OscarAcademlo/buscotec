<?php
// backend/file_proxy.php
declare(strict_types=1);

/*
 Sirve adjuntos por ID sin exponer rutas físicas.
 URL: backend/file_proxy.php?id=123
*/

ini_set('display_errors', '0');
error_reporting(E_ALL);
header_remove('X-Powered-By');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

require_once __DIR__ . '/conexion.php';

// Buscar adjunto
$stmt = $conn->prepare("SELECT id, ruta, mime, peso FROM mensaje_adjuntos WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || !$res->num_rows) {
  http_response_code(404);
  echo 'Not found';
  exit;
}
$adj = $res->fetch_assoc();
$stmt->close();

$ruta = (string)($adj['ruta'] ?? '');
$mime = trim((string)($adj['mime'] ?? ''));
$peso = (int)($adj['peso'] ?? 0);

// Si la ruta es absoluta http(s), redirigimos
if (preg_match('#^https?://#i', $ruta)) {
  header('Location: ' . $ruta, true, 302);
  exit;
}

// Candidatos de búsqueda en el filesystem (ajusta si fuera necesario)
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/');
$candidates = [
  $DOCROOT . '/backend/uploads/mensajes/',
  $DOCROOT . '/uploads/mensajes/',
  $DOCROOT . '/backend/uploads/',
  $DOCROOT . '/uploads/',
  // fallback relativos por si los de arriba no calzan
  __DIR__ . '/uploads/mensajes/',
  __DIR__ . '/uploads/',
  dirname(__DIR__) . '/uploads/mensajes/',
  dirname(__DIR__) . '/uploads/',
];

// Si la ruta ya incluye subcarpetas, probamos directo también
$tryList = [];
if ($ruta !== '') {
  // si empieza con /, interpretamos como absoluta dentro del docroot
  if ($ruta[0] === '/') {
    $tryList[] = $DOCROOT . $ruta;
  } else {
    // solo nombre o subruta relativa
    foreach ($candidates as $base) {
      $tryList[] = rtrim($base, '/') . '/' . ltrim($ruta, '/');
    }
  }
}

// Elegir el primer archivo existente
$found = null;
foreach ($tryList as $path) {
  if (is_file($path)) { $found = $path; break; }
}

if (!$found) {
  http_response_code(404);
  echo 'File not found';
  exit;
}

// Deducción de MIME si falta
if ($mime === '') {
  $ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
  $map = [
    'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
    'pdf'=>'application/pdf','mp4'=>'video/mp4','mov'=>'video/quicktime','mp3'=>'audio/mpeg'
  ];
  $mime = $map[$ext] ?? 'application/octet-stream';
}

// Cabeceras
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($found) . '"');
header('Cache-Control: public, max-age=31536000, immutable');

$size = filesize($found);
if ($size !== false) header('Content-Length: ' . $size);

// Enviar archivo
readfile($found);
