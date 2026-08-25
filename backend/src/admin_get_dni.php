<?php
// backend/admin_get_dni.php
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Solo administradores
$email = $_SESSION['email'] ?? '';
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
if (!in_array(strtolower($email), $ADMIN_ALLOWLIST)) {
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos de administrador']);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'profe';

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

// Si es USER, no tiene documentos de profesional (por ahora)
if ($tipo === 'user') {
    echo json_encode(['ok' => true, 'has_dni' => false, 'data' => null]);
    exit;
}

try {
    $q = $conn->prepare("SELECT dni_frente, dni_dorso, fecha_subida FROM profesionales_documentos WHERE profesional_id = ? LIMIT 1");
    $q->bind_param("i", $id);
    $q->execute();
    $res = $q->get_result();
    $doc = $res->fetch_assoc();
    $q->close();

    // Fetch birth date from profesionales_datos
    $q2 = $conn->prepare("SELECT fecha_nacimiento FROM profesionales_datos WHERE profesional_id = ? LIMIT 1");
    $q2->bind_param("i", $id);
    $q2->execute();
    $res2 = $q2->get_result();
    $birth = $res2->fetch_assoc();
    $q2->close();

    if ($birth) {
        if (!$doc) $doc = ['dni_frente' => null, 'dni_dorso' => null, 'fecha_subida' => null];
        $doc['fecha_nacimiento'] = $birth['fecha_nacimiento'];
    }

    // --- MEJORA: Búsqueda dinámica si no hay registro o falta el frente ---
    if (!$doc || !$doc['dni_frente']) {
        // Obtener nombre del profesional para buscar archivos huerfanos
        $qp = $conn->prepare("SELECT nombre, apellido FROM profesionales WHERE id = ? LIMIT 1");
        $qp->bind_param("i", $id);
        $qp->execute();
        $pData = $qp->get_result()->fetch_assoc();
        $qp->close();

        if ($pData) {
            $nombre = trim($pData['nombre']);
            $apellido = trim($pData['apellido']);
            
            // Función interna para normalizar (quitar acentos)
            // Función interna para normalizar (quitar acentos)
            $eliminarAcentos = function($str) {
                $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
                return strtr($str, $unwanted_array);
            };

            $bases = [
                strtolower(preg_replace('/\s+/', '_', $nombre . '_' . $apellido)),
                strtolower(preg_replace('/\s+/', '_', $eliminarAcentos($nombre) . '_' . $eliminarAcentos($apellido)))
            ];

            $dniDir = __DIR__ . '/../img/dni/';
            $foundF = null;
            $foundD = null;

            foreach ($bases as $base) {
                $patterns = [
                    $dniDir . $base . '_dni_frente.*',
                    $dniDir . $base . '_dni_dorso.*',
                    $dniDir . $base . '_frente.*', // Algunos no traen "_dni_"
                    $dniDir . $base . '_dorso.*'
                ];
                foreach ($patterns as $pattern) {
                    $files = glob($pattern);
                    if (!empty($files)) {
                        $f = basename($files[0]);
                        if (stripos($f, 'frente') !== false) { if(!$foundF) $foundF = 'img/dni/'.$f; }
                        elseif (stripos($f, 'dorso') !== false) { if(!$foundD) $foundD = 'img/dni/'.$f; }
                    }
                }
                if ($foundF) break;
            }

            if ($foundF || $foundD) {
                // Actualizar o Insertar en caliente
                if ($doc) {
                    $upd = $conn->prepare("UPDATE profesionales_documentos SET dni_frente = IFNULL(dni_frente, ?), dni_dorso = IFNULL(dni_dorso, ?) WHERE profesional_id = ?");
                    $upd->bind_param("ssi", $foundF, $foundD, $id);
                    $upd->execute();
                    $upd->close();
                } else {
                    $ins = $conn->prepare("INSERT INTO profesionales_documentos (profesional_id, dni_frente, dni_dorso) VALUES (?, ?, ?)");
                    $ins->bind_param("iss", $id, $foundF, $foundD);
                    $ins->execute();
                    $ins->close();
                }
                
                // Refrescar $doc pero MANTENER lo que ya teníamos (como fecha_nacimiento)
                if (!$doc) $doc = [];
                $doc['dni_frente'] = $foundF;
                $doc['dni_dorso'] = $foundD;
                $doc['fecha_subida'] = 'Auto-detectado';
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'has_dni' => (bool)($doc && $doc['dni_frente']),
        'data' => $doc
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
