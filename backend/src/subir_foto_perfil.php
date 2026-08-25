<?php
// backend/subir_foto_perfil.php — BLINDADO
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Validar Sesión
if (empty($_SESSION['id'])) {
    echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Por favor, reingresa.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$role = $_POST['role'] ?? 'usuario';

// Seguridad: El ID enviado debe coincidir con el de la sesión (o estar en sus roles)
$session_role_id = (int) ($_SESSION['role_ids'][$role] ?? 0);
if ($id !== $session_role_id && (int)$_SESSION['id'] !== $id) {
     echo json_encode(['ok' => false, 'error' => 'No tienes permiso para actualizar este perfil.']);
     exit;
}

if (!$id || !isset($_FILES['foto'])) {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos o imagen.']);
    exit;
}

// Directorio de subida
$uploadDir = __DIR__ . '/../img/';
if ($role === 'profesional') {
    $uploadDir .= 'profesionales/';
} else {
    $uploadDir .= 'usuarios/';
}

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Procesar archivo
$file = $_FILES['foto'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($ext, $allowed_exts)) {
    echo json_encode(['ok' => false, 'error' => 'Formato de imagen no permitido (usar JPG, PNG o WEBP).']);
    exit;
}

$filename = $id . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $filename;

// Mover archivo
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // URL relativa para guardar en BD
    $dbPath = ($role === 'profesional') ? "img/profesionales/$filename" : "img/usuarios/$filename";

    // Actualizar BD
    if ($role === 'profesional') {
        $sql = "UPDATE profesionales SET foto_profesional = ? WHERE id = ?";
    } else {
        $sql = "UPDATE usuarios SET foto = ? WHERE id = ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'Error de base de datos interno.']);
        exit;
    }
    
    $stmt->bind_param("si", $dbPath, $id);

    if ($stmt->execute()) {
        // Devolvemos la URL completa para visualización inmediata
        echo json_encode([
            'ok' => true, 
            'url' => $dbPath, // La app ya concatena la url base o usa relativa
            'full_url' => "https://buscotec.com.ar/" . $dbPath,
            'msg' => 'Foto actualizada correctamente.'
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Error al actualizar base de datos.']);
    }
} else {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo. Verifica permisos de carpeta.']);
}