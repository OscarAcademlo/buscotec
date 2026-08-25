<?php
// backend/mensajes_unificado.php
require_once __DIR__ . "/session_boot.php";
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_GET['user_id'] ?? ($_SESSION['id'] ?? 0)) ?: null;
$rolActivo = (string) ($_GET['role'] ?? ($_SESSION['role'] ?? ''));
$roles = $_SESSION['roles'] ?? [];
$role_ids = $_SESSION['role_ids'] ?? [];

// Si tenemos userId pero no rolActivo, intentamos inferirlo
if ($userId && !$rolActivo) {
  if (isset($_GET['role'])) {
    $rolActivo = $_GET['role'];
  } else {
    $rolActivo = 'usuario'; // Fallback
  }
}

// 🔧 RECONSTRUCCIÓN DE ROLES PARA LA APP (si no hay sesión)
if ($userId && empty($role_ids)) {
  require_once __DIR__ . '/conexion.php';
  if ($conn) {
    // Intentar buscar por ID en ambas tablas para saber qué roles tiene
    // Primero como usuario
    $stmtU = $conn->prepare("SELECT id, email FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtU) {
      $stmtU->bind_param('i', $userId);
      $stmtU->execute();
      $resU = $stmtU->get_result();
      if ($rowU = $resU->fetch_assoc()) {
        $role_ids['usuario'] = (int) $rowU['id'];
        $_SESSION['email'] = $rowU['email']; // Ayuda a session_boot
      }
      $stmtU->close();
    }
    // También como profesional (con el mismo email o ID si coincide)
    // Nota: en BuscoTec a veces el ID de usuario y profesional para la misma persona es distinto.
    // Si tenemos el email del paso anterior, buscamos al profesional por email.
    $email = $_SESSION['email'] ?? '';
    if ($email) {
      $stmtP = $conn->prepare("SELECT id FROM profesionales WHERE email = ? LIMIT 1");
      if ($stmtP) {
        $stmtP->bind_param('s', $email);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        if ($rowP = $resP->fetch_assoc()) {
          $role_ids['profesional'] = (int) $rowP['id'];
        }
        $stmtP->close();
      }
    }
  }
  // Fallback mínimo
  if (empty($role_ids)) {
    $role_ids[$rolActivo ?: 'usuario'] = $userId;
  }
}

// =========================================================================
// 🚑 INTENTO DE RECUPERACIÓN POR JWT (Igual que en enviar_mensaje.php)
// =========================================================================
if (!$userId) {
  if (isset($_COOKIE['bt_jwt'])) {
    require_once __DIR__ . '/jwt_helper.php';
    // Unificado con login.php y session_from_jwt.php
    $JWT_SECRET = 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA_2026';

    try {
      $data = jwt_decode($_COOKIE['bt_jwt'], $JWT_SECRET);
      if ($data && !empty($data['uid'])) { // El JWT usa 'uid' según login.php
        $userId = (int) $data['uid'];
        $rolActivo = (string) ($data['role'] ?? 'usuario');
        $roles = $data['roles'] ?? [$rolActivo];
        $role_ids = $data['role_ids'] ?? [$rolActivo => $userId];

        $_SESSION['id'] = $userId;
        $_SESSION['role'] = $rolActivo;
        $_SESSION['roles'] = $roles;
        $_SESSION['role_ids'] = $role_ids;
      }
    } catch (Throwable $e) {
      error_log("mensajes_unificado: Error JWT " . $e->getMessage());
    }
  }
}

// =========================================================================

if (!$userId) {
  echo json_encode(['ok' => false, 'error' => 'Sesión inválida']);
  exit;
}

try {
  // ============================================================
  // FILTROS DINÁMICOS - CORREGIDO: usa userId + rolActivo directamente
  // ============================================================
  $condsRec = [];
  $paramsRec = [];
  $typesRec = '';

// 🔧 RECONSTRUCCIÓN DE ROLES PARA LA APP (Siempre priorizar lo que manda la App)
if ($userId && $rolActivo) {
    if (empty($role_ids)) {
        $role_ids[$rolActivo] = $userId;
    }
}

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

  // 🔧 FIX FINAL: Si aún está vacío, usar userId directo con ambos tipos
  if (empty($condsRec) && $userId) {
    $condsRec[] = "(m.destinatario_id = ? AND m.destinatario_tipo IN ('usuario', 'profesional'))";
    $paramsRec[] = $userId;
    $typesRec .= 'i';
  }

  if (empty($condsRec)) {
    error_log("BuscoTec ERROR: mensajes_unificado.php - No se encontraron roles para userId: $userId, role: $rolActivo");
    echo json_encode(['ok' => false, 'error' => 'Sin roles válidos']);
    exit;
  }

  $whereRec = implode(' OR ', $condsRec);
  error_log("BuscoTec DEBUG: mensajes_unificado.php - userId: $userId, role: $rolActivo, whereRec: $whereRec, role_ids: " . json_encode($role_ids));

  // ============================================================
  // RECIBIDOS
  // ============================================================
  // CORRECCIÓN: nombre de tabla mensaje_adjuntos (singular)
  $sqlRec = "
    SELECT 
        m.id, m.mensaje, m.fecha_envio, m.leido, m.caso_id,
        m.remitente_id, m.remitente_tipo, m.tipo, m.estado_respuesta,
        CASE 
          WHEN m.remitente_tipo = 'sistema' THEN 'BuscoTec'
          ELSE COALESCE(ur.nombre, pr.nombre) 
        END AS nombre,
        CASE 
          WHEN m.remitente_tipo = 'sistema' THEN '(Sistema)'
          ELSE COALESCE(ur.apellido, pr.apellido) 
        END AS apellido,
        COALESCE(ur.whatsapp, pr.whatsapp) AS whatsapp,
        a.ruta, a.mime, a.peso
    FROM mensajes m
    LEFT JOIN usuarios ur       ON (m.remitente_tipo = 'usuario'     AND ur.id = m.remitente_id)
    LEFT JOIN profesionales pr  ON (m.remitente_tipo = 'profesional' AND pr.id = m.remitente_id)
    LEFT JOIN mensaje_adjuntos a ON a.mensaje_id = m.id
    WHERE ($whereRec)
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
        'id' => $row['id'],
        'caso_id' => $row['caso_id'] ?? 0,
        'mensaje' => $row['mensaje'],
        'fecha_envio' => $row['fecha_envio'],
        'leido' => $row['leido'],
        'nombre' => $row['nombre'],
        'apellido' => $row['apellido'],
        'tipo' => $row['tipo'],
        'estado' => $row['estado_respuesta'],
        'whatsapp' => ($row['estado_respuesta'] === 'aceptado') ? ($row['whatsapp'] ?? '') : '',
        'de_nombre' => trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')), // 🚑 COMPATIBILIDAD APP ANDROID
        'adjuntos' => []
      ];
    }

    // Si hay archivo, lo agregamos al array adjuntos
    if (!empty($row['ruta'])) {
      // FUERZA BRUTA: Solo nos importa el nombre del archivo, ignoramos cualquier ruta anterior
      $fileName = basename($row['ruta']);
      $webPath = "uploads_mensajes/$fileName";

      $recibidos[$id]['adjuntos'][] = [
        'url' => $webPath,
        'mime' => $row['mime'],
        'peso' => (int) $row['peso']
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
            m.id, m.mensaje, m.fecha_envio, m.leido, m.caso_id,
            m.destinatario_id, m.destinatario_tipo, m.tipo, m.estado_respuesta,
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
        'id' => $row['id'],
        'caso_id' => $row['caso_id'] ?? 0,
        'mensaje' => $row['mensaje'],
        'fecha_envio' => $row['fecha_envio'],
        'leido' => $row['leido'],
        'nombre' => $row['nombre'],
        'apellido' => $row['apellido'],
        'tipo' => $row['tipo'],
        'estado' => $row['estado_respuesta'],
        'de_nombre' => trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')), // 🚑 COMPATIBILIDAD APP ANDROID
        'adjuntos' => []
      ];
    }

    if (!empty($row['ruta'])) {
      // FUERZA BRUTA: Solo nos importa el nombre del archivo
      $fileName = basename($row['ruta']);
      $webPath = "uploads_mensajes/$fileName";

      $enviados[$id]['adjuntos'][] = [
        'url' => $webPath,
        'mime' => $row['mime'],
        'peso' => (int) $row['peso']
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
    'enviados' => $enviados,
    'roles' => $roles,
    'role_ids' => $role_ids,
    'rol_activo' => $rolActivo
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
