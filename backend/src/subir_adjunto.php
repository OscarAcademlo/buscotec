<?php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Logs
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/error_log.log');
error_reporting(E_ALL);

// ✅ Verificar login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

// ✅ Validar parámetros
if (empty($_POST['mensaje_id']) || !isset($_FILES['archivo'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan parámetros']);
    exit;
}

$mensaje_id = (int)$_POST['mensaje_id'];
$file = $_FILES['archivo'];

// ✅ Validar cantidad (máx 3 archivos por mensaje)
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM mensaje_adjuntos WHERE mensaje_id = ?");
$stmt->bind_param("i", $mensaje_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res['total'] >= 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Máximo 3 adjuntos por mensaje']);
    exit;
}

// ✅ Validar errores de subida
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Error al subir archivo']);
    exit;
}

// ✅ Validar MIME permitido
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Formato no permitido']);
    exit;
}

// ✅ Validar tamaño (máx 5 MB)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Archivo demasiado grande']);
    exit;
}

// ✅ Guardar archivo en carpeta public_html/uploads_mensajes/
$upload_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads_mensajes/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0775, true);
}

// ✅ Nombre de archivo: mensajeID_nombreOriginalLimpio
$original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$nombre_archivo = "{$mensaje_id}_" . $original_name;

$ruta_absoluta = $upload_dir . $nombre_archivo;

if (!move_uploaded_file($file['tmp_name'], $ruta_absoluta)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al guardar archivo']);
    exit;
}

// ✅ Insertar en BD
// Se cambia la variable a $nombre_archivo
$stmt = $conn->prepare("INSERT INTO mensaje_adjuntos (mensaje_id, ruta, mime, peso) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $mensaje_id, $nombre_archivo, $mime, $file['size']);
$stmt->execute();

echo json_encode([
    'ok' => true,
    'message' => 'Archivo subido correctamente',
    'ruta' => $nombre_archivo, // <- Se retorna solo el nombre del archivo
    'mime' => $mime,
    'peso' => $file['size']
]);