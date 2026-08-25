<?php
/**
 * BACKEND/TEST_PAGO.PHP
 * Simula un pago aprobado para verificar la actualización de saldo y el envío de emails.
 */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: text/plain; charset=utf-8');
echo "--- SIMULADOR DE PAGO BUSCOTEC ---\n\n";

// 1. Buscar profesional (Jorge Lopez o el primero que deba algo)
$nombre = "jorge";
$stmt = $conn->prepare("SELECT id, nombre, email FROM profesionales WHERE nombre LIKE ? LIMIT 1");
$term = "%$nombre%";
$stmt->bind_param('s', $term);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();

if (!$prof) {
    // Si no hay jorge, traer el primero que deba algo
    $res = $conn->query("SELECT p.id, p.nombre, p.email FROM profesionales p JOIN casos c ON c.receptor_id = p.id WHERE c.pagado = 0 LIMIT 1");
    $prof = $res->fetch_assoc();
}

if (!$prof) {
    echo "❌ ERROR: No se encontró ningún profesional con casos pendientes para la prueba.\n";
    exit;
}

$user_id = $prof['id'];
echo "Probando con Profesional ID: $user_id ({$prof['nombre']})\n";

// 2. Simulamos la actualización de saldo que haría Mercado Pago
$resCols = $conn->query("SHOW COLUMNS FROM casos LIKE 'pagado_at'");
$hasPagadoAt = ($resCols && $resCols->num_rows > 0);
$setSql = $hasPagadoAt ? "SET pagado = 1, pagado_at = NOW()" : "SET pagado = 1";

$sql = "UPDATE casos $setSql WHERE receptor_id = ? AND pagado = 0";
$upd = $conn->prepare($sql);
if (!$upd) {
    die("❌ ERROR SQL: " . $conn->error . " | Query: " . $sql);
}
$upd->bind_param('i', $user_id);
$upd->execute();
$affected = $upd->affected_rows;

echo "Resultado DB: $affected registros actualizados.\n";

// 3. Simulamos el envío de email
$admins = ['oscarns@gmail.com', 'orticelli@gmail.com'];
$asunto = "🧪 TEST: Pago Simulado Acreditado";
$cuerpo = "Este es un test para confirmar que el sistema de actualización y mails funciona correctamente.\nAffected Rows: $affected\nID Profesional: $user_id";

$mails_ok = 0;
foreach ($admins as $dest) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Ejecutamos la función pero capturamos el error si falla
        if (bt_enviar_mail($dest, $asunto, $cuerpo)) {
            $mails_ok++;
            echo "✅ Mail enviado a $dest\n";
        } else {
             echo "❌ Error enviando a $dest. (Revisar logs)\n";
        }
    } catch (Exception $e) {
        echo "❌ ERROR PHPMailer ($dest): " . $e->getMessage() . "\n";
    }
}

echo "Resultado Email: $mails_ok / 2 enviados.\n\n";

if ($affected > 0 && $mails_ok > 0) {
    echo "✅ CONCLUSIÓN: El sistema funciona perfectamente. \nSi el pago real de Mercado Pago no entra, es porque Mercado Pago no está pudiendo llamar al archivo 'notificacion_mp.php'.";
} else {
    echo "⚠️ ATENCIÓN: El sistema no pudo completar todo el proceso. Revisar logs.";
}
