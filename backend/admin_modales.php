<?php
// backend/admin_modales.php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false, 'error' => 'Acceso no autorizado'];

// ===== VERIFICACIÓN DE SEGURIDAD (ADMIN ONLY) =====
$adminAllowlist = ['oscarns@gmail.com', 'orticelli@gmail.com'];
$sessionEmail = strtolower(trim($_SESSION['email'] ?? ''));
$sessionUserId = (string)($_SESSION['user_id'] ?? '');

$isAuthorized = false;
if ($sessionEmail && in_array($sessionEmail, $adminAllowlist, true)) {
    $isAuthorized = true;
} elseif ($sessionUserId === '10' || $sessionUserId === '11') {
    $isAuthorized = true;
}

// Fallback por header o post si la sesión PHP expiró pero viene email válido
$reqEmail = strtolower(trim($_REQUEST['admin_email'] ?? ''));
if (!$isAuthorized && $reqEmail && in_array($reqEmail, $adminAllowlist, true)) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== AUTO-CREACIÓN DE LA TABLA IF NOT EXISTS =====
if (isset($conn) && $conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS modales_programados (
          id INT AUTO_INCREMENT PRIMARY KEY,
          titulo VARCHAR(255) NOT NULL,
          texto TEXT NOT NULL,
          imagen_principal VARCHAR(500) NULL,
          sub_titulo_1 VARCHAR(255) NULL,
          sub_texto_1 TEXT NULL,
          sub_imagen_1 VARCHAR(500) NULL,
          sub_titulo_2 VARCHAR(255) NULL,
          sub_texto_2 TEXT NULL,
          sub_imagen_2 VARCHAR(500) NULL,
          fecha_inicio DATETIME NOT NULL,
          fecha_fin DATETIME NOT NULL,
          activo TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión a base de datos']);
    exit;
}

$action = $_REQUEST['action'] ?? 'listar';

// ===== FUNCIÓN DE PROCESAMIENTO DE ARCHIVOS ADJUNTOS =====
function procesarSubidaImagen(string $fieldKey, string $defaultUrl = ''): string {
    if (!isset($_FILES[$fieldKey]) || $_FILES[$fieldKey]['error'] !== UPLOAD_ERR_OK) {
        return $defaultUrl;
    }
    
    $tmpName = $_FILES[$fieldKey]['tmp_name'];
    $name = $_FILES[$fieldKey]['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        return $defaultUrl;
    }
    
    $targetDir = __DIR__ . '/../uploads_perfiles/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    
    $newName = 'modal_' . uniqid('', true) . '.' . $ext;
    $targetFile = $targetDir . $newName;
    
    if (move_uploaded_file($tmpName, $targetFile)) {
        return 'uploads_perfiles/' . $newName;
    }
    
    return $defaultUrl;
}

try {
    switch ($action) {
        case 'listar':
            $res = $conn->query("SELECT * FROM modales_programados ORDER BY id DESC");
            $modales = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $modales[] = [
                        'id' => (int)$row['id'],
                        'titulo' => $row['titulo'],
                        'texto' => $row['texto'],
                        'imagen_principal' => $row['imagen_principal'],
                        'sub_titulo_1' => $row['sub_titulo_1'],
                        'sub_texto_1' => $row['sub_texto_1'],
                        'sub_imagen_1' => $row['sub_imagen_1'],
                        'sub_titulo_2' => $row['sub_titulo_2'],
                        'sub_texto_2' => $row['sub_texto_2'],
                        'sub_imagen_2' => $row['sub_imagen_2'],
                        'fecha_inicio' => $row['fecha_inicio'],
                        'fecha_fin' => $row['fecha_fin'],
                        'activo' => (int)$row['activo'],
                        'created_at' => $row['created_at']
                    ];
                }
            }
            $response = ['ok' => true, 'modales' => $modales];
            break;

        case 'guardar':
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $texto = trim((string)($_POST['texto'] ?? ''));
            $fecha_inicio = trim((string)($_POST['fecha_inicio'] ?? ''));
            $fecha_fin = trim((string)($_POST['fecha_fin'] ?? ''));
            $activo = isset($_POST['activo']) && ($_POST['activo'] === '1' || $_POST['activo'] === 1 || $_POST['activo'] === 'on') ? 1 : 0;

            if ($titulo === '' || $texto === '' || $fecha_inicio === '' || $fecha_fin === '') {
                echo json_encode(['ok' => false, 'error' => 'Título, texto y fechas (inicio y fin) son requeridos']);
                exit;
            }

            // Imágenes (Subida o URL enviada)
            $imgPrincipal = trim((string)($_POST['imagen_principal'] ?? ''));
            $imgPrincipal = procesarSubidaImagen('file_imagen_principal', $imgPrincipal);

            $subTitulo1 = trim((string)($_POST['sub_titulo_1'] ?? ''));
            $subTexto1 = trim((string)($_POST['sub_texto_1'] ?? ''));
            $subImg1 = trim((string)($_POST['sub_imagen_1'] ?? ''));
            $subImg1 = procesarSubidaImagen('file_sub_imagen_1', $subImg1);

            $subTitulo2 = trim((string)($_POST['sub_titulo_2'] ?? ''));
            $subTexto2 = trim((string)($_POST['sub_texto_2'] ?? ''));
            $subImg2 = trim((string)($_POST['sub_imagen_2'] ?? ''));
            $subImg2 = procesarSubidaImagen('file_sub_imagen_2', $subImg2);

            // Formatear fechas a Y-m-d H:i:s
            $fecha_inicio_sql = date('Y-m-d H:i:s', strtotime($fecha_inicio));
            $fecha_fin_sql = date('Y-m-d H:i:s', strtotime($fecha_fin));

            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE modales_programados SET
                        titulo = ?,
                        texto = ?,
                        imagen_principal = ?,
                        sub_titulo_1 = ?,
                        sub_texto_1 = ?,
                        sub_imagen_1 = ?,
                        sub_titulo_2 = ?,
                        sub_texto_2 = ?,
                        sub_imagen_2 = ?,
                        fecha_inicio = ?,
                        fecha_fin = ?,
                        activo = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "sssssssssssii",
                    $titulo, $texto, $imgPrincipal,
                    $subTitulo1, $subTexto1, $subImg1,
                    $subTitulo2, $subTexto2, $subImg2,
                    $fecha_inicio_sql, $fecha_fin_sql, $activo,
                    $id
                );
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO modales_programados (
                        titulo, texto, imagen_principal,
                        sub_titulo_1, sub_texto_1, sub_imagen_1,
                        sub_titulo_2, sub_texto_2, sub_imagen_2,
                        fecha_inicio, fecha_fin, activo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssssssi",
                    $titulo, $texto, $imgPrincipal,
                    $subTitulo1, $subTexto1, $subImg1,
                    $subTitulo2, $subTexto2, $subImg2,
                    $fecha_inicio_sql, $fecha_fin_sql, $activo
                );
                $stmt->execute();
                $id = $stmt->insert_id;
                $stmt->close();
            }

            $response = ['ok' => true, 'id' => $id, 'mensaje' => 'Modal guardado correctamente'];
            break;

        case 'borrar':
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM modales_programados WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $response = ['ok' => true, 'mensaje' => 'Modal eliminado correctamente'];
            } else {
                $response = ['ok' => false, 'error' => 'ID inválido'];
            }
            break;

        case 'toggle':
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE modales_programados SET activo = IF(activo = 1, 0, 1) WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $response = ['ok' => true, 'mensaje' => 'Estado actualizado correctamente'];
            } else {
                $response = ['ok' => false, 'error' => 'ID inválido'];
            }
            break;

        default:
            $response = ['ok' => false, 'error' => 'Acción no reconocida'];
            break;
    }
} catch (Throwable $e) {
    $response = ['ok' => false, 'error' => 'Error de servidor: ' . $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
