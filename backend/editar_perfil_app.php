<?php
// backend/editar_perfil_app.php
// Endpoint dedicado para la App Flutter — autenticación sin sesión PHP
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
// CORS para la app web Flutter
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['http://localhost:3000', 'https://buscotec.click', 'https://oscarsoft.click'], true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id, X-User-Role, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function outApp(array $data): void
{
    if (ob_get_length())
        ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Leer identidad del request
$uid = (int) ($_SERVER['HTTP_X_USER_ID'] ?? $_POST['_uid'] ?? $_POST['id'] ?? 0);
$role = trim($_SERVER['HTTP_X_USER_ROLE'] ?? $_POST['_role'] ?? $_POST['role'] ?? 'usuario');

if (!$uid) {
    outApp(['ok' => false, 'error' => 'ID requerido. Re-iniciá sesión.']);
}

// 2. Roles
if ($role === 'profesional') {
    $profId = $uid;
    $userId = null;
} else {
    $userId = $uid;
    $profId = null;
}

// 3. Leer datos POST
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$barrio = trim($_POST['barrio'] ?? '');
$ciudad = trim($_POST['ciudad'] ?? '');
$provincia = trim($_POST['provincia'] ?? '');

if (empty($nombre) || empty($apellido) || empty($email)) {
    outApp(['ok' => false, 'error' => 'Nombre, apellido y email son obligatorios.']);
}

$success = true;
$errors = [];

// 4. Actualizar USUARIO
if ($userId) {
    $stmt = $conn->prepare(
        "UPDATE usuarios SET nombre=?, apellido=?, email=?, whatsapp=?, direccion=?, barrio=?, ciudad=?, provincia=? WHERE id=? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('ssssssssi', $nombre, $apellido, $email, $whatsapp, $direccion, $barrio, $ciudad, $provincia, $userId);
        if (!$stmt->execute()) {
            $success = false;
            $errors[] = "Error al actualizar usuario: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $success = false;
        $errors[] = "Error preparando consulta de usuario.";
    }
}

// 5. Actualizar PROFESIONAL
if ($profId) {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $experiencia = $_POST['experiencia'] !== null && isset($_POST['experiencia']) && $_POST['experiencia'] !== ''
        ? (int) $_POST['experiencia']
        : -1;

    $sql = "UPDATE profesionales SET nombre=?, apellido=?, email=?, whatsapp=?, direccion=?, barrio=?, ciudad=?, provincia=?";
    $params = [$nombre, $apellido, $email, $whatsapp, $direccion, $barrio, $ciudad, $provincia];
    $types = "ssssssss";

    if ($descripcion !== '') {
        $sql .= ", descripcion=?";
        $params[] = $descripcion;
        $types .= "s";
    }
    if ($experiencia >= 0) {
        $sql .= ", experiencia=?";
        $params[] = $experiencia;
        $types .= "i";
    }

    $sql .= " WHERE id=? LIMIT 1";
    $params[] = $profId;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $success = false;
            $errors[] = "Error al actualizar profesional: " . $stmt->error;
        }
        $stmt->close();

        // Actualizar categorías si se enviaron
        if (isset($_POST['categorias'])) {
            $cats = is_array($_POST['categorias'])
                ? $_POST['categorias']
                : explode(',', $_POST['categorias']);

            $del = $conn->prepare("DELETE FROM profesional_categorias WHERE profesional_id = ?");
            if ($del) {
                $del->bind_param('i', $profId);
                $del->execute();
                $del->close();
            }

            $ins = $conn->prepare("INSERT IGNORE INTO profesional_categorias (profesional_id, categoria_id) VALUES (?, ?)");
            $primaryCatId = 0;
            if ($ins) {
                foreach ($cats as $catId) {
                    $cId = (int) $catId;
                    if ($cId > 0) {
                        $ins->bind_param('ii', $profId, $cId);
                        $ins->execute();
                        if ($primaryCatId === 0)
                            $primaryCatId = $cId;
                    }
                }
                $ins->close();
            }

            if ($primaryCatId > 0) {
                $upd = $conn->prepare("UPDATE profesionales SET categoria_id = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param('ii', $primaryCatId, $profId);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
    } else {
        $success = false;
        $errors[] = "Error preparando consulta de profesional.";
    }
}

if ($success) {
    outApp(['ok' => true, 'msg' => 'Perfil actualizado correctamente.']);
} else {
    outApp(['ok' => false, 'error' => implode(' | ', $errors)]);
}
