<?php
// ============================================================
// backend/editar_perfil.php — versión funcional (2025)
// ============================================================
declare(strict_types=1);
ob_start();

require_once __DIR__ . "/session_boot.php";

// Depuración: Loguear sesión (temporal)
file_put_contents(__DIR__ . '/debug_session.log', "[" . date('Y-m-d H:i:s') . "] ID: " . session_id() . " SESSION: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

function out(array $r): void
{
    if (ob_get_length())
        ob_clean();
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

// 🟨 Validar sesión (Flexible como en get_perfil.php)
if (empty($_SESSION['id']) && empty($_SESSION['user_id'])) {
    out(['ok' => false, 'error' => 'Sesión no activa (ID: ' . session_id() . '). Por favor re-iniciá sesión.']);
}

$id = (int) ($_SESSION['id'] ?? $_SESSION['user_id']);
$roleIds = $_SESSION['role_ids'] ?? [];
$role = $_SESSION['role'] ?? 'usuario';

// Mapeo flexible de IDs
$userId = $roleIds['usuario'] ?? $roleIds['cliente'] ?? null;
$profId = $roleIds['profesional'] ?? null;

// Fallback si role_ids está vacío (usamos el id de la sesión)
if (empty($roleIds)) {
    if ($role === 'profesional')
        $profId = $id;
    else
        $userId = $id;
}

// 📥 Recibir datos POST
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$barrio = trim($_POST['barrio'] ?? '');
$ciudad = trim($_POST['ciudad'] ?? '');
$provincia = trim($_POST['provincia'] ?? '');

if (empty($nombre) || empty($apellido) || empty($email)) {
    out(['ok' => false, 'error' => 'Nombre, apellido y email son obligatorios.']);
}

$success = true;
$errors = [];

// 🟦 Actualizar datos de USUARIO (si tiene el rol)
if ($userId) {
    $uid = (int) $userId;
    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, email=?, whatsapp=?, direccion=?, barrio=?, ciudad=?, provincia=? WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ssssssssi', $nombre, $apellido, $email, $whatsapp, $direccion, $barrio, $ciudad, $provincia, $uid);
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

// 🟩 Actualizar datos de PROFESIONAL (si tiene el rol)
if ($profId) {
    $pid = (int) $profId;

    // Aquí podrías agregar más campos si el formulario los envía (experiencia, descripcion, etc.)
    $descripcion = trim($_POST['descripcion'] ?? '');
    $experiencia = (int) ($_POST['experiencia'] ?? -1);

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
    $params[] = $pid;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $success = false;
            $errors[] = "Error al actualizar profesional: " . $stmt->error;
        }
        $stmt->close();

        // Actualizar categorías del profesional
        // 1. Eliminar anteriores
        $delSql = "DELETE FROM profesional_categorias WHERE profesional_id = ?";
        $delStmt = $conn->prepare($delSql);
        if ($delStmt) {
            $delStmt->bind_param('i', $pid);
            $delStmt->execute();
            $delStmt->close();
        }

        // 2. Insertar nuevas
        if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
            $insSql = "INSERT INTO profesional_categorias (profesional_id, categoria_id) VALUES (?, ?)";
            $insStmt = $conn->prepare($insSql);
            $primaryCatId = 0; // Para legacy

            if ($insStmt) {
                foreach ($_POST['categorias'] as $catId) {
                    $cId = (int) $catId;
                    if ($cId > 0) {
                        $insStmt->bind_param('ii', $pid, $cId);
                        $insStmt->execute();
                        // Guardar el primero como primario
                        if ($primaryCatId === 0)
                            $primaryCatId = $cId;
                    }
                }
                $insStmt->close();
            }

            // 3. Actualizar categoría primaria (Legacy)
            if ($primaryCatId > 0) {
                $updLegacy = $conn->prepare("UPDATE profesionales SET categoria_id = ? WHERE id = ?");
                if ($updLegacy) {
                    $updLegacy->bind_param('ii', $primaryCatId, $pid);
                    $updLegacy->execute();
                    $updLegacy->close();
                }
            }
        }
    } else {
        $success = false;
        $errors[] = "Error preparando consulta de profesional.";
    }
}

if ($success) {
    // Actualizar datos en la sesión para que se vean reflejados inmediatamente
    $_SESSION['nombre'] = $nombre . ' ' . $apellido;
    $_SESSION['email'] = $email;

    out(['ok' => true, 'msg' => 'Perfil actualizado correctamente.']);
} else {
    out(['ok' => false, 'error' => implode(' | ', $errors)]);
}
?>