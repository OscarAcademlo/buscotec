<?php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$userId     = $_SESSION['id']    ?? null;
$roles      = $_SESSION['roles'] ?? [];
$role_ids   = $_SESSION['role_ids'] ?? [];
$rolActivo  = $_SESSION['role'] ?? null;

if (!$userId || empty($roles)) {
    echo json_encode(['ok' => false, 'error' => 'Sesión inválida']);
    exit;
}

try {
    // ============================================================
    // FILTROS DINÁMICOS
    // ============================================================
    $condsRec = [];
    $paramsRec = [];
    $typesRec = '';

    if (!empty($role_ids['usuario'])) {
        $condsRec[] = "(m.destinatario_id = ? AND m.destinatario_tipo = 'usuario')";
        $paramsRec[] = $role_ids['usuario'];
        $typesRec .= 'i';
    }

    if (!empty($role_ids['profesional'])) {
        $condsRec[] = "(m.destinatario_id = ? AND m.destinatario_tipo = 'profesional')";
        $paramsRec[] = $role_ids['profesional'];
        $typesRec .= 'i';
    }

    if (empty($condsRec)) {
        echo json_encode(['ok' => false, 'error' => 'Sin roles válidos']);
        exit;
    }

    $whereRec = implode(' OR ', $condsRec);

   // ============================================================
// RECIBIDOS
// ============================================================
$sqlRec = "
    SELECT 
        m.id, m.mensaje, m.fecha_envio, m.leido,
        m.remitente_id, m.remitente_tipo, m.tipo,
        COALESCE(ur.nombre, pr.nombre) AS nombre,
        COALESCE(ur.apellido, pr.apellido) AS apellido,
        a.ruta, a.mime, a.peso
    FROM mensajes m
    LEFT JOIN usuarios ur       ON (m.remitente_tipo = 'usuario'     AND ur.id = m.remitente_id)
    LEFT JOIN profesionales pr  ON (m.remitente_tipo = 'profesional' AND pr.id = m.remitente_id)
   LEFT JOIN mensajes_adjuntos a ON a.mensaje_id = m.id


    WHERE (
        $whereRec
        OR m.tipo = 'sistema'
    )
    ORDER BY m.fecha_envio DESC
    LIMIT 100
";

$stmtRec = $conn->prepare($sqlRec);
$stmtRec->bind_param($typesRec, ...$paramsRec);
$stmtRec->execute();
$resRec = $stmtRec->get_result();

$recibidos = [];
while ($row = $resRec->fetch_assoc()) {
    $id = $row['id'];

    // Inicializa el mensaje si no existe
    if (!isset($recibidos[$id])) {
        $recibidos[$id] = [
            'id'          => $row['id'],
            'mensaje'     => $row['mensaje'],
            'fecha_envio' => $row['fecha_envio'],
            'leido'       => $row['leido'],
            'nombre'      => $row['nombre'],
            'apellido'    => $row['apellido'],
            'tipo'        => $row['tipo'],  // 👈 importante para detectar mensajes del sistema
            'adjuntos'    => []
        ];
    }

    // Si hay archivo, lo agregamos al array adjuntos
    if (!empty($row['ruta'])) {
        $ruta = trim($row['ruta']);
        if (!str_contains($ruta, 'uploads_mensajes')) {
            $ruta = '/uploads_mensajes/' . $ruta;
        }
        $recibidos[$id]['adjuntos'][] = [
            'ruta' => $ruta,
            'mime' => $row['mime'],
            'peso' => (int)$row['peso']
        ];
    }
}
$recibidos = array_values($recibidos); // Reindexar


    // ============================================================
    // ENVIADOS
    // ============================================================
    $condsEnv = [];
    $paramsEnv = [];
    $typesEnv = '';

    if (!empty($role_ids['usuario'])) {
        $condsEnv[] = "(m.remitente_id = ? AND m.remitente_tipo = 'usuario')";
        $paramsEnv[] = $role_ids['usuario'];
        $typesEnv .= 'i';
    }

    if (!empty($role_ids['profesional'])) {
        $condsEnv[] = "(m.remitente_id = ? AND m.remitente_tipo = 'profesional')";
        $paramsEnv[] = $role_ids['profesional'];
        $typesEnv .= 'i';
    }

    $whereEnv = implode(' OR ', $condsEnv);

    $sqlEnv = "
        SELECT 
            m.id, m.mensaje, m.fecha_envio, m.leido,
            m.destinatario_id, m.destinatario_tipo,
            COALESCE(ud.nombre, pd.nombre) AS nombre,
            COALESCE(ud.apellido, pd.apellido) AS apellido,
            a.ruta, a.mime, a.peso
        FROM mensajes m
        LEFT JOIN usuarios ud       ON (m.destinatario_tipo = 'usuario'     AND ud.id = m.destinatario_id)
        LEFT JOIN profesionales pd  ON (m.destinatario_tipo = 'profesional' AND pd.id = m.destinatario_id)
        LEFT JOIN mensaje_adjuntos a ON a.mensaje_id = m.id
        WHERE $whereEnv
        ORDER BY m.fecha_envio DESC
        LIMIT 100
    ";

    $stmtEnv = $conn->prepare($sqlEnv);
    $stmtEnv->bind_param($typesEnv, ...$paramsEnv);
    $stmtEnv->execute();
    $resEnv = $stmtEnv->get_result();

    $enviados = [];
    while ($row = $resEnv->fetch_assoc()) {
        $id = $row['id'];
        if (!isset($enviados[$id])) {
            $enviados[$id] = [
                'id'         => $row['id'],
                'mensaje'    => $row['mensaje'],
                'fecha_envio'=> $row['fecha_envio'],
                'leido'      => $row['leido'],
                'nombre'     => $row['nombre'],
                'apellido'   => $row['apellido'],
                'adjuntos'   => []
            ];
        }

        if (!empty($row['ruta'])) {
            $ruta = trim($row['ruta']);
            if (!str_contains($ruta, 'uploads_mensajes')) {
                $ruta = '/uploads_mensajes/' . $ruta;
            }
            $enviados[$id]['adjuntos'][] = [
                'ruta' => $ruta,
                'mime' => $row['mime'],
                'peso' => (int)$row['peso']
            ];
        }
    }
    $enviados = array_values($enviados);

    // ============================================================
    // RESPUESTA JSON
    // ============================================================
    echo json_encode([
        'ok' => true,
        'recibidos' => $recibidos,
        'enviados'  => $enviados,
        'roles'     => $roles,
        'role_ids'  => $role_ids,
        'rol_activo'=> $rolActivo
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
