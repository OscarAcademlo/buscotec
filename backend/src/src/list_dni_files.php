<?php
// backend/list_dni_files.php
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';

header('Content-Type: application/json; charset=utf-8');

// Permisos (solo oscar y orticelli)
$email = $_SESSION['email'] ?? '';
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
if (!in_array(strtolower($email), $ADMIN_ALLOWLIST)) {
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos de administrador']);
    exit;
}

$dir = __DIR__ . '/../img/dni/';
$files = [];

if (is_dir($dir)) {
    $dirFiles = scandir($dir);
    foreach ($dirFiles as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($dir . $file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $files[] = $file;
            }
        }
    }
}

// Ordenar alfabéticamente
sort($files);

echo json_encode([
    'ok' => true,
    'files' => $files
]);
?>