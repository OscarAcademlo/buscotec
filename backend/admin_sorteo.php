<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/conexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/src/Exception.php';

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conn) {
    json_out(['ok' => false, 'msg' => 'Error de conexión con la base de datos'], 500);
}

// === VALIDACIÓN DEFENSIVA DE BASE DE DATOS ===
// 1. Asegurar que la columna email_enviado existe
$chkCol = $conn->query("SHOW COLUMNS FROM `sorteo_participantes` LIKE 'email_enviado'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `sorteo_participantes` ADD COLUMN `email_enviado` TINYINT DEFAULT 0 AFTER `numero_sorteo`");
}
// 2. Eliminar de forma segura la restricción UNIQUE antigua sobre numero_sorteo si existe
$chkIdx = $conn->query("SHOW INDEX FROM `sorteo_participantes`");
if ($chkIdx) {
    $hasUnique = false;
    while ($row = $chkIdx->fetch_assoc()) {
        if (($row['Key_name'] ?? '') === 'numero_sorteo') {
            $hasUnique = true;
            break;
        }
    }
    if ($hasUnique) {
        @$conn->query("ALTER TABLE `sorteo_participantes` DROP INDEX `numero_sorteo`");
    }
}
// 3. Reparar defensivamente registros antiguos sin fecha (les asigna NOW() para agruparlos en el sorteo activo)
@$conn->query("UPDATE `sorteo_participantes` SET `created_at` = NOW() WHERE `created_at` IS NULL OR `created_at` = '0000-00-00 00:00:00' OR `created_at` = ''");

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $periodo = $_GET['periodo'] ?? $_POST['periodo'] ?? '';
    
    // Buscar el periodo más reciente con registros si no se especificó uno
    if (empty($periodo)) {
        $resMax = $conn->query("SELECT MAX(DATE_FORMAT(created_at, '%Y-%m')) AS max_per FROM sorteo_participantes");
        if ($resMax && $rowMax = $resMax->fetch_assoc()) {
            $periodo = $rowMax['max_per'] ?? '';
        }
        if (empty($periodo)) {
            $periodo = date('Y-m');
        }
    }

    $periodo_escaped = $conn->real_escape_string($periodo);

    // Obtener concursantes ordenados para el período seleccionado
    $sql = "SELECT id, email, telefono, nombre, apellido, referido_nombre, referido_instagram, numero_sorteo, email_enviado, created_at 
            FROM sorteo_participantes 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = '$periodo_escaped' 
            ORDER BY email, created_at ASC";
    $res = $conn->query($sql);
    if (!$res) {
        json_out(['ok' => false, 'msg' => 'Error al consultar la tabla sorteo_participantes: ' . $conn->error]);
    }
    
    $concursantes = [];
    while ($row = $res->fetch_assoc()) {
        $email = strtolower(trim($row['email']));
        if (!isset($concursantes[$email])) {
            $concursantes[$email] = [
                'nombre' => $row['nombre'],
                'apellido' => $row['apellido'],
                'email' => $email,
                'telefono' => $row['telefono'],
                'tickets' => [],
                'total_tickets' => 0,
                'tickets_enviados' => 0
            ];
        }

        $concursantes[$email]['tickets'][] = [
            'id' => (int)$row['id'],
            'numero_sorteo' => str_pad((string)$row['numero_sorteo'], 4, '0', STR_PAD_LEFT),
            'referido_nombre' => $row['referido_nombre'],
            'referido_instagram' => $row['referido_instagram'],
            'email_enviado' => (int)$row['email_enviado'],
            'created_at' => $row['created_at']
        ];

        $concursantes[$email]['total_tickets']++;
        if ((int)$row['email_enviado'] === 1) {
            $concursantes[$email]['tickets_enviados']++;
        }
    }

    // Obtener todos los períodos únicos cargados para alimentar el desplegable
    $periodos = [date('Y-m')];
    $resP = $conn->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS per FROM sorteo_participantes ORDER BY per DESC");
    if ($resP) {
        while ($row = $resP->fetch_assoc()) {
            if (!empty($row['per']) && !in_array($row['per'], $periodos)) {
                $periodos[] = $row['per'];
            }
        }
    }
    sort($periodos);
    $periodos = array_reverse($periodos);

    // Convertir a un array indexado para facilitar la iteración en JS
    json_out([
        'ok' => true,
        'concursantes' => array_values($concursantes),
        'periodos' => $periodos,
        'periodo_activo' => $periodo
    ]);

} elseif ($action === 'send_email') {
    // Obtener los datos desde POST o JSON
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
    $id = (int)($data['id'] ?? $_POST['id'] ?? 0);

    if ($id <= 0) {
        json_out(['ok' => false, 'msg' => 'ID de participación inválido.']);
    }

    // Obtener la fila de participación
    $stmt = $conn->prepare("SELECT email, nombre, apellido, numero_sorteo FROM sorteo_participantes WHERE id = ? LIMIT 1");
    if (!$stmt) {
        json_out(['ok' => false, 'msg' => 'Error al preparar la consulta.']);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        json_out(['ok' => false, 'msg' => 'No se encontró la participación con el ID especificado.']);
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    $email = trim($row['email']);
    $nombre = trim($row['nombre']);
    $apellido = trim($row['apellido']);
    $numeroFormatted = str_pad((string)$row['numero_sorteo'], 4, '0', STR_PAD_LEFT);

    // Enviar email con PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $config = require __DIR__ . '/config/mailer.env.php';
        $mail->Host = $config['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['SMTP_USER'];
        $mail->Password = $config['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('oscarns@gmail.com', 'BuscoTec');
        $mail->addAddress($email, "$nombre $apellido");
        $mail->isHTML(true);
        $mail->Subject = 'Tu número para el sorteo - BuscoTec 🎁';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #1877f2;'>Hola $nombre,</h2>
                <p style='font-size: 1.1rem; line-height: 1.5;'>Somos de <strong>BuscoTec</strong>.</p>
                <p style='font-size: 1.2rem; line-height: 1.5; background-color: #f0f7ff; padding: 15px; border-radius: 8px; border-left: 5px solid #1877f2;'>
                    Tu número para el sorteo es el <strong>$numeroFormatted</strong>.
                </p>
                <p style='font-size: 1rem; color: #555;'>¡Mucha suerte en el sorteo de Junio 2026!</p>
                <hr style='border: 0; border-top: 1px solid #eee;'>
                <p style='font-size: 0.85rem; color: #888;'>Este es un mensaje automático. No respondas a este correo.</p>
            </div>
        ";

        $mail->send();

        // Actualizar base de datos
        $stmtUp = $conn->prepare("UPDATE sorteo_participantes SET email_enviado = 1 WHERE id = ?");
        if ($stmtUp) {
            $stmtUp->bind_param("i", $id);
            $stmtUp->execute();
            $stmtUp->close();
        }

        json_out([
            'ok' => true,
            'msg' => 'Email enviado con éxito al participante.',
            'id' => $id,
            'numero_sorteo' => $numeroFormatted
        ]);

    } catch (Exception $e) {
        error_log("MAIL_ERROR_SORTEO: " . $e->getMessage());
        json_out(['ok' => false, 'msg' => 'Error al enviar el correo a través de SMTP: ' . $e->getMessage()]);
    }
} elseif ($action === 'delete_concursante') {
    // Obtener los datos desde POST o JSON
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
    $email = trim((string)($data['email'] ?? $_POST['email'] ?? ''));
    $periodo = $_GET['periodo'] ?? $_POST['periodo'] ?? '';
    if (empty($periodo)) {
        $periodo = date('Y-m');
    }

    if ($email === '') {
        json_out(['ok' => false, 'msg' => 'Email del concursante inválido.']);
    }

    $emailNorm = mb_strtolower($email);
    $email_esc = $conn->real_escape_string($emailNorm);
    $periodo_esc = $conn->real_escape_string($periodo);

    $sql = "DELETE FROM sorteo_participantes WHERE email = '$email_esc' AND DATE_FORMAT(created_at, '%Y-%m') = '$periodo_esc'";
    if ($conn->query($sql)) {
        json_out([
            'ok' => true,
            'msg' => 'Concursante eliminado con éxito en este período.'
        ]);
    } else {
        json_out(['ok' => false, 'msg' => 'Error al eliminar el concursante de la base de datos: ' . $conn->error]);
    }
} elseif ($action === 'buscar_numero') {
    $num = isset($_GET['numero']) ? (int)$_GET['numero'] : -1;
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 3;
    $periodo = $_GET['periodo'] ?? $_POST['periodo'] ?? '';
    if (empty($periodo)) {
        $periodo = date('Y-m');
    }
    
    if ($num < 0 || $num > 9999) {
        json_out(['ok' => false, 'msg' => 'Número inválido. Debe estar entre 0000 y 9999.']);
    }
    if ($limite <= 0) {
        $limite = 3;
    }
    
    $num_esc = (int)$num;
    $limite_esc = (int)$limite;
    $periodo_esc = $conn->real_escape_string($periodo);

    // Obtener los N más cercanos ordenando por diferencia absoluta.
    $sql = "SELECT id, email, telefono, nombre, apellido, numero_sorteo, ABS(numero_sorteo - $num_esc) AS diferencia 
            FROM sorteo_participantes 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = '$periodo_esc' 
            ORDER BY diferencia ASC, numero_sorteo ASC 
            LIMIT $limite_esc";
    $res = $conn->query($sql);
    if (!$res) {
        json_out(['ok' => false, 'msg' => 'Error al buscar el ganador: ' . $conn->error]);
    }
    
    $ganadores = [];
    while ($row = $res->fetch_assoc()) {
        $row['numero_sorteo'] = str_pad((string)$row['numero_sorteo'], 4, '0', STR_PAD_LEFT);
        $row['es_exacto'] = ((int)$row['diferencia'] === 0);
        $ganadores[] = $row;
    }
    
    if (empty($ganadores)) {
        json_out(['ok' => false, 'msg' => 'No se encontraron participantes para el período seleccionado.']);
    }
    
    json_out([
        'ok' => true,
        'ganadores' => $ganadores
    ]);
} elseif ($action === 'asignar_numeros_faltantes') {
    $periodo = $_GET['periodo'] ?? $_POST['periodo'] ?? '';
    if (empty($periodo)) {
        $periodo = date('Y-m');
    }

    $periodo_esc = $conn->real_escape_string($periodo);

    // 1. Obtener todos los emails únicos que ya están participando en el período seleccionado
    $existentes = [];
    $resExist = $conn->query("SELECT DISTINCT email FROM sorteo_participantes WHERE DATE_FORMAT(created_at, '%Y-%m') = '$periodo_esc'");
    if (!$resExist) {
         json_out(['ok' => false, 'msg' => 'Error al consultar participantes existentes: ' . $conn->error]);
    }
    while ($row = $resExist->fetch_assoc()) {
        $existentes[strtolower(trim($row['email']))] = true;
    }

    // 2. Obtener usuarios
    $usuarios = [];
    $resUsers = $conn->query("SELECT nombre, apellido, email, whatsapp FROM usuarios");
    if ($resUsers) {
        while ($row = $resUsers->fetch_assoc()) {
            $email = strtolower(trim($row['email'] ?? ''));
            if ($email !== '' && !isset($existentes[$email])) {
                $usuarios[$email] = [
                    'nombre' => trim($row['nombre'] ?? ''),
                    'apellido' => trim($row['apellido'] ?? ''),
                    'telefono' => trim($row['whatsapp'] ?? ''),
                    'email' => $email
                ];
            }
        }
    }

    // 3. Obtener profesionales
    $profesionales = [];
    $resProfs = $conn->query("SELECT nombre, apellido, email, whatsapp FROM profesionales");
    if ($resProfs) {
        while ($row = $resProfs->fetch_assoc()) {
            $email = strtolower(trim($row['email'] ?? ''));
            if ($email !== '' && !isset($existentes[$email])) {
                $profesionales[$email] = [
                    'nombre' => trim($row['nombre'] ?? ''),
                    'apellido' => trim($row['apellido'] ?? ''),
                    'telefono' => trim($row['whatsapp'] ?? ''),
                    'email' => $email
                ];
            }
        }
    }

    // Combinar
    $faltantes = array_merge($usuarios, $profesionales);

    if (empty($faltantes)) {
        json_out([
            'ok' => true,
            'msg' => 'Todos los usuarios y profesionales ya tienen un número asignado para este período.',
            'agregados' => 0
        ]);
    }

    // 4. Asignar un número único a cada faltante
    $agregados = 0;
    $conn->begin_transaction();
    try {
        foreach ($faltantes as $email => $p) {
            // Generar número de sorteo único de 4 cifras para el período seleccionado
            $numero_sorteo = 0;
            $max_intentos = 200;
            $generado_ok = false;
            
            for ($i = 0; $i < $max_intentos; $i++) {
                $candidato = mt_rand(0, 9999);
                
                // Verificar si el número ya existe en este período
                $candidato_int = (int)$candidato;
                $resChk = $conn->query("SELECT id FROM sorteo_participantes WHERE numero_sorteo = $candidato_int AND DATE_FORMAT(created_at, '%Y-%m') = '$periodo_esc' LIMIT 1");
                $existe = ($resChk && $resChk->num_rows > 0);
                
                if (!$existe) {
                    $numero_sorteo = $candidato;
                    $generado_ok = true;
                    break;
                }
            }

            if (!$generado_ok) {
                throw new Exception("No se pudo generar un número único en el período {$periodo} para " . $email);
            }

            $createdAtVal = date('Y-m-d H:i:s');
            if ($periodo !== date('Y-m')) {
                $createdAtVal = $periodo . "-01 12:00:00";
            }

            $email_esc = $conn->real_escape_string($p['email']);
            $tel_esc = $conn->real_escape_string($p['telefono']);
            $nom_esc = $conn->real_escape_string($p['nombre']);
            $ape_esc = $conn->real_escape_string($p['apellido']);
            $num_sorteo_int = (int)$numero_sorteo;
            $created_esc = $conn->real_escape_string($createdAtVal);

            $sqlInsert = "INSERT INTO sorteo_participantes (email, telefono, nombre, apellido, referido_nombre, referido_instagram, numero_sorteo, created_at) 
                          VALUES ('$email_esc', '$tel_esc', '$nom_esc', '$ape_esc', '', '', $num_sorteo_int, '$created_esc')";
            
            if (!$conn->query($sqlInsert)) {
                throw new Exception("Error al insertar concursante: " . $conn->error);
            }
            $agregados++;
        }
        
        $conn->commit();
        
        json_out([
            'ok' => true,
            'msg' => "Se asignaron números al azar correctamente a {$agregados} concursantes nuevos en el período {$periodo}.",
            'agregados' => $agregados
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        json_out([
            'ok' => false,
            'msg' => 'Error al asignar números: ' . $e->getMessage()
        ], 500);
    }
} else {
    json_out(['ok' => false, 'msg' => 'Acción no permitida.']);
}
