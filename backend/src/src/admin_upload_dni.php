<?php
// backend/admin_upload_dni.php
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Solo administradores (puedes ajustar esta lógica según tu sistema de roles)
$email = $_SESSION['email'] ?? '';
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
if (!in_array(strtolower($email), $ADMIN_ALLOWLIST)) {
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos de administrador']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = (int) ($_POST['profesional_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID de profesional inválido']);
    exit;
}

if (!isset($_FILES['dni_file']) || $_FILES['dni_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Error al subir el archivo']);
    exit;
}

try {
    // Obtener nombre y apellido para el nombre del archivo
    $q = $conn->prepare("SELECT nombre, apellido FROM profesionales WHERE id = ? LIMIT 1");
    $q->bind_param("i", $id);
    $q->execute();
    $res = $q->get_result();
    $p = $res->fetch_assoc();
    $q->close();

    if (!$p) {
        throw new Exception("Profesional no encontrado");
    }

    $nombre = trim($p['nombre']);
    $apellido = trim($p['apellido']);
    $baseName = strtolower(preg_replace('/\s+/', '_', $nombre . '_' . $apellido));

    $ext = strtolower(pathinfo($_FILES['dni_file']['name'], PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        throw new Exception("Formato no permitido. Solo JPG o PNG.");
    }

    $fileName = "{$baseName}_dni_frente.{$ext}";
    $targetDir = __DIR__ . '/../img/dni/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['dni_file']['tmp_name'], $targetFile)) {
        echo json_encode([
            'ok' => true,
            'msg' => 'Imagen cargada correctamente',
            'path' => "img/dni/{$fileName}"
        ]);
    } else {
        throw new Exception("No se pudo mover el archivo al destino.");
    }

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>