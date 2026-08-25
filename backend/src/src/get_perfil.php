<?php
// backend/get_perfil.php — Blindado
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Validar sesión
if (empty($_SESSION['id'])) {
    // ⚠️ NO responder 'ok'=>false ni 'error' para no disparar logout en frontend si es un fallo tonto.
    // Solo si estamos SEGURÍSIMOS que no hay sesión, mandamos null data.
    out(['ok' => false, 'error' => 'No session']);
}

// 2. Normalizar datos de sesión (Auto-Repair ya sucedió en boot)
$id = (int) $_SESSION['id'];
$role = $_SESSION['role'] ?? 'usuario';
$roles = $_SESSION['roles'] ?? [$role];
$role_ids = $_SESSION['role_ids'] ?? [];

// Fallback crítico si role_ids sigue vacío (muy raro gracias a boot)
if (empty($role_ids)) {
    $role_ids = [$role => $id];
}

// 3. Obtener Datos
function getRow($conn, $table, $id)
{
    if (!$id || !$conn)
        return null;

    if ($table === 'usuarios') {
        $sql = "SELECT id, nombre, apellido, email, whatsapp, direccion, barrio, ciudad, provincia, verificado FROM usuarios WHERE id = ? LIMIT 1";
    } else {
        $sql = "SELECT p.*, c.nombre as categoria_nombre 
                FROM profesionales p 
                LEFT JOIN categorias c ON c.id = p.categoria_id 
                WHERE p.id = ? LIMIT 1";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt)
        return null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data && $table === 'profesionales') {
        // Obtener categorías múltiples
        $catSql = "SELECT cp.categoria_id, c.nombre 
                   FROM profesional_categorias cp
                   JOIN categorias c ON c.id = cp.categoria_id
                   WHERE cp.profesional_id = ?";
        $catStmt = $conn->prepare($catSql);
        if ($catStmt) {
            $catStmt->bind_param('i', $id);
            $catStmt->execute();
            $catResult = $catStmt->get_result();

            $catIds = [];
            $catNombres = [];

            while ($row = $catResult->fetch_assoc()) {
                $catIds[] = (int) $row['categoria_id'];
                $catNombres[] = $row['nombre'];
            }

            $data['categorias'] = $catIds;
            $data['categorias_nombres'] = !empty($catNombres) ? implode(', ', $catNombres) : '';

            // Si no hay categorías múltiples pero hay una 'categoria_id' (legacy), agregarla
            if (empty($catIds) && !empty($data['categoria_id'])) {
                $data['categorias'][] = (int) $data['categoria_id'];
                if (empty($data['categorias_nombres']) && !empty($data['categoria_nombre'])) {
                    $data['categorias_nombres'] = $data['categoria_nombre'];
                }
            }
        }
    }

    return $data;
}

$usuario = null;
$profesional = null;

if (!empty($role_ids['usuario'])) {
    $usuario = getRow($conn, 'usuarios', (int) $role_ids['usuario']);
}
if (!empty($role_ids['profesional'])) {
    $profesional = getRow($conn, 'profesionales', (int) $role_ids['profesional']);
}

// 4. Respuesta Segura
$nombre = $_SESSION['nombre']
    ?? ($usuario ? ($usuario['nombre'] . ' ' . ($usuario['apellido'] ?? '')) : null)
    ?? ($profesional ? $profesional['nombre'] : null)
    ?? 'Usuario';

out([
    'ok' => true,
    'data' => [
        'id' => $id,
        'role' => $role,
        'usuario' => $usuario,
        'profesional' => $profesional,
        'nombre' => $nombre,
        'roles' => $roles,
        'role_ids' => $role_ids,
        'session_id' => session_id(),
        'pending_messages' => 0
    ]
]);
