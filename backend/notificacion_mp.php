<?php
/**
 * BACKEND/NOTIFICACION_MP.PHP - VERSIÓN FINAL BLINDADA 2026
 */
declare(strict_types=1);

// 1. Log de emergencia inmediato (para saber si MP llega al servidor)
$log_file = __DIR__ . '/emergency_mp.log';
$raw_body = file_get_contents('php://input');
$entry = "[" . date('Y-m-d H:i:s') . "] PING RECIBIDO | GET: " . json_encode($_GET) . " | BODY: " . $raw_body . "\n";
file_put_contents($log_file, $entry, FILE_APPEND);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mailer.php';
$mp_config = require __DIR__ . '/config/mercadopago.php';
$access_token = $mp_config['access_token'];

// 2. Extraer ID de la operación
$data = json_decode($raw_body, true);
$id = $data['data']['id'] ?? ($_GET['id'] ?? ($_GET['data_id'] ?? ''));
$type = $data['type'] ?? ($_GET['topic'] ?? ($_GET['type'] ?? 'payment'));

if (empty($id)) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: No se detectó ID de pago.\n", FILE_APPEND);
    http_response_code(200);
    exit;
}

// 3. Consultar a Mercado Pago si el pago es REAL y está APROBADO
$url = "https://api.mercadopago.com/v1/payments/" . $id;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$payment = json_decode((string)$response, true);
$status = $payment['status'] ?? '';
$external_reference = $payment['external_reference'] ?? '';
$monto = $payment['transaction_amount'] ?? 0;

file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] MP RESPONSE: Status=$status | Ref=$external_reference | Code=$http_code\n", FILE_APPEND);

    // 4. Procesar solo si está aprobado
    if ($status === 'approved') {
        $affected = 0;
        
        // Verificar si existe pagado_at
        $resCols = $conn->query("SHOW COLUMNS FROM casos LIKE 'pagado_at'");
        $hasPagadoAt = ($resCols && $resCols->num_rows > 0);
        
        $setSql = $hasPagadoAt ? "SET pagado = 1, pagado_at = NOW()" : "SET pagado = 1";

        if (strpos((string)$external_reference, 'CASO_') === 0) {
            $id_caso = (int)str_replace('CASO_', '', $external_reference);
            $stmt = $conn->prepare("UPDATE casos $setSql WHERE id = ?");
            $stmt->bind_param('i', $id_caso);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
        } 
        elseif (strpos((string)$external_reference, 'TOTAL_') === 0) {
            $user_id = (int)str_replace('TOTAL_', '', $external_reference);
            
            // También registrar el pago en el historial como hace el admin
            $sqlHist = "INSERT INTO pagos_profesionales (profesional_id, monto, nota, registrado_por) VALUES (?, ?, 'Mercado Pago Pago Total', 'mp')";
            $stHist = $conn->prepare($sqlHist);
            if ($stHist) {
                $stHist->bind_param('id', $user_id, $monto);
                $stHist->execute();
                $stHist->close();
            }

            $stmt = $conn->prepare("UPDATE casos $setSql WHERE receptor_id = ? AND pagado = 0");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
        }

    // 5. Enviar Email a los Admins (SIEMPRE después de intentar actualizar)
    $admins = ['oscarns@gmail.com', 'orticelli@gmail.com'];
    $asunto = "💰 PAGO RECIBIDO: " . $external_reference;
    $monto_f = "$" . number_format((float)$monto, 2, ',', '.');
    
    $cuerpo = "
        <div style='font-family:sans-serif; border:1px solid #ccc; padding:20px;'>
            <h2 style='color:#28a745;'>¡Pago Acreditado!</h2>
            <p>Se ha recibido y procesado un pago de Mercado Pago.</p>
            <hr>
            <p><b>Referencia:</b> $external_reference</p>
            <p><b>Monto:</b> $monto_f</p>
            <p><b>Filas Actualizadas:</b> $affected</p>
            <p><b>ID Mercado Pago:</b> $id</p>
            <hr>
            <p style='color:#888; font-size:11px;'>Si 'Filas Actualizadas' es 0, significa que el saldo ya estaba en 0 o no se encontró al profesional.</p>
        </div>
    ";

    foreach ($admins as $dest) {
        bt_enviar_mail($dest, $asunto, $cuerpo);
    }
}

// 6. Responder siempre 200 a MP
http_response_code(200);
echo "OK";
