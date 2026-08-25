<?php
// backend/retrace_dni.php
require_once 'conexion.php';
header('Content-Type: text/plain; charset=utf-8');

echo "--- RASTREANDO DNI EXISTENTES ---\n\n";

try {
    // Obtener todos los profesionales
    $res = $conn->query("SELECT id, nombre, apellido FROM profesionales");
    $profesionales = $res->fetch_all(MYSQLI_ASSOC);

    $dniDir = __DIR__ . '/../img/dni/';
    if (!is_dir($dniDir)) {
        echo "La carpeta img/dni no existe.\n";
        exit;
    }

    $matched = 0;
    foreach ($profesionales as $p) {
        $id = $p['id'];
        $nombre = trim($p['nombre']);
        $apellido = trim($p['apellido']);

        $eliminarAcentos = function($str) {
            $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
            return strtr($str, $unwanted_array);
        };

        $bases = [
            strtolower(preg_replace('/\s+/', '_', $nombre . '_' . $apellido)),
            strtolower(preg_replace('/\s+/', '_', $eliminarAcentos($nombre) . '_' . $eliminarAcentos($apellido)))
        ];
        
        $pathF = null;
        $pathD = null;

        foreach ($bases as $base) {
            $patternsF = [
                $dniDir . $base . '_dni_frente.*',
                $dniDir . $base . '_frente.*'
            ];
            $patternsD = [
                $dniDir . $base . '_dni_dorso.*',
                $dniDir . $base . '_dorso.*'
            ];

            foreach ($patternsF as $patt) {
                $files = glob($patt);
                if (!empty($files)) {
                    $pathF = 'img/dni/' . basename($files[0]);
                    break;
                }
            }
            foreach ($patternsD as $patt) {
                $files = glob($patt);
                if (!empty($files)) {
                    $pathD = 'img/dni/' . basename($files[0]);
                    break;
                }
            }
            if ($pathF || $pathD) break;
        }

        if ($pathF || $pathD) {
            // Verificar si ya tiene registro
            $check = $conn->prepare("SELECT id FROM profesionales_documentos WHERE profesional_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$exists) {
                $ins = $conn->prepare("INSERT INTO profesionales_documentos (profesional_id, dni_frente, dni_dorso) VALUES (?, ?, ?)");
                $ins->bind_param("iss", $id, $pathF, $pathD);
                $ins->execute();
                echo "✅ Profesional #$id ({$p['nombre']} {$p['apellido']}): DNI vinculado.\n";
                $matched++;
            } else {
                echo "ℹ️ Profesional #$id ({$p['nombre']} {$p['apellido']}): Ya tiene registro.\n";
            }
        }
    }

    echo "\nTotal vinculados: $matched\n";

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
