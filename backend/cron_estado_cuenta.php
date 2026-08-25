<?php
// backend/cron_estado_cuenta.php
// SE EJECUTA DESDE CRON (o forzado vía test)
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mailer.php';

// Prevenir Timeouts de servidor (LiteSpeed/Apache) al enviar muchos correos
@set_time_limit(0);
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(true);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

if (!($conn instanceof mysqli)) {
    die("Error: Sin conexión a DB\n");
}
$conn->set_charset('utf8mb4');

// Asegurar columnas de las tablas
$conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS creditos INT DEFAULT 0");
$conn->query("ALTER TABLE casos ADD COLUMN IF NOT EXISTS pagado TINYINT(1) DEFAULT 0");

$force = isset($_GET['force']) && $_GET['force'] == '1';
$solo_deudores = isset($_GET['solo_deudores']) && $_GET['solo_deudores'] == '1';
$preview = isset($_GET['preview']) && $_GET['preview'] == '1';

// 1. OBTENER CONFIGURACIONES
$ajustes = [];
$res = $conn->query("SELECT clave, valor FROM ajustes");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $ajustes[$row['clave']] = $row['valor'];
    }
}

$diaConfigurado = strtolower(trim($ajustes['dia_cobro'] ?? 'lunes'));
$horaConfigurada = trim($ajustes['hora_cobro'] ?? '10');
$mensajeExtra = trim($ajustes['mensaje_extra'] ?? '');
$cronActivado = (int)($ajustes['cron_activado'] ?? 1);
$valorCaso = (float)($ajustes['valor_caso'] ?? 1400.00);



// Nombres de los días en PHP (0=Sunday, 1=Monday...)
$diasMap = [
    '0' => 'domingo', '1' => 'lunes', '2' => 'martes',
    '3' => 'miércoles', '4' => 'jueves', '5' => 'viernes', '6' => 'sábado'
];
$hoy = $diasMap[date('w')];
$horaActual = date('H'); // Hora actual en formato 00 a 23
// Quitar ceros a la izquierda para comparar 09 con 9
$horaActualInt = (int)$horaActual;
$horaConfInt = (int)$horaConfigurada;

// Quitar tilde a miércoles para comparar más fácil si es necesario, o exact match:
$hoyClean = str_replace('é', 'e', $hoy);
$diaConfClean = str_replace('é', 'e', $diaConfigurado);

if (!$force) {
    if ($cronActivado === 0) {
        die("El envío automático de mensajes está DESACTIVADO desde el panel de administración.\n");
    }
    if ($hoyClean !== $diaConfClean) {
        die("Hoy es $hoy, el cobro automático está configurado para $diaConfigurado. No se ejecuta nada.\n");
    }
    if ($horaActualInt !== $horaConfInt) {
        die("La hora actual es $horaActualInt:00, el cobro está configurado para las $horaConfInt:00. No se ejecuta nada.\n");
    }
}

if (!$preview) {
    echo "Iniciando proceso de cobros automáticos ($hoy)...\n";
}

$preview_data = [];

$qProfes = $conn->query("
    SELECT id, nombre, apellido, email, whatsapp, creditos, verificado 
    FROM profesionales 
    WHERE estado_servicio = 1 OR suspedido = 0
");
// Mejor: todos los verificados y activos
$qProfes = $conn->query("
    SELECT id, nombre, apellido, email, whatsapp, creditos 
    FROM profesionales 
    WHERE verificado = 1 
");

if (!$qProfes) {
    die("Error leyendo profesionales.\n");
}

$enviados = 0;

while ($pro = $qProfes->fetch_assoc()) {
    $prof_id = (int)$pro['id'];
    $creditos = (int)$pro['creditos'];
    $nombre = $pro['nombre'] . ' ' . $pro['apellido'];
    $email = $pro['email'];

    // Calcular deuda (Extraemos todo lo aceptado)
    $deuda = 0;
    $casos_sin_pagar = 0;
    $qDeuda = $conn->query("SELECT cargo_ars, cargo_usd, pagado FROM casos WHERE receptor_id={$prof_id} AND LOWER(estado)='aceptado'");
    if ($qDeuda) {
        while($c = $qDeuda->fetch_assoc()) {
            $is_pagado = (int)($c['pagado'] ?? 0);
            if ($is_pagado === 0) {
                $casos_sin_pagar++;
                $cargoArs = (float)($c['cargo_ars'] ?? 0);
                $cargoUsd = (float)($c['cargo_usd'] ?? 0);
                
                if ($cargoArs > 0) {
                    $deuda += $cargoArs;
                } else if ($cargoUsd > 0 && $cargoUsd >= 50) {
                    $deuda += $cargoUsd;
                } else {
                    $deuda += $valorCaso;
                }
            }
        }
    }


    // if ($deuda == 0 && $creditos == 0 && empty($mensajeExtra)) {
    //    continue; // DETIENE EL SPAM PARA NO QUEDAR MAL: No le enviamos nada a los usuarios con $0 de deuda y 0 creditos, a menos que haya un mensaje global.
    // }

    if ($solo_deudores && $deuda == 0) {
        continue; // Filtro de admin: Saltar si no debe plata (aunque tenga créditos)
    }

    // FORMULAR MENSAJE HTML
    $asunto = "Tu estado de cuenta de BuscoTec";
    $mensajeHTML = "";
    $mensajeApp = "";

    if ($deuda > 0) {
        // TIENE DEUDA -> ABONAR
        $mensajeHTML = "
            <h2>Resumen global de tu cuenta</h2>
            <p>Tenés <b>$casos_sin_pagar casos</b> generados en total pendientes de pago.</p>
            <p style='font-size: 1.2rem; margin:15px 0;'>El saldo acumulado a abonar es de: <b>$" . number_format($deuda, 2, ',', '.') . " ARS</b></p>
            
            <div style='background: #fff8e1; border: 1px solid #ffe082; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #856404; font-weight: bold;'>⚠️ AVISO DE SEGURIDAD:</p>
                <p style='margin: 5px 0 0 0; color: #856404;'>Nunca te pediremos ni enviaremos links de pago directo por correo para evitar estafas. Todos los pagos se realizan de forma segura leyendo tus mensajes internos dentro de la aplicación.</p>
            </div>

            <a href='https://buscotec.com.ar/pagos.html' style='display:inline-block; padding:12px 24px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:100px; font-weight:bold;'>Abrir App para leer mensajes y abonar</a>
            <p style='margin-top:15px; font-size:12px; color:#666;'>Si ya abonaste, desestimá este correo. Recordá que mantener tu saldo al día te permite seguir recibiendo trabajos.</p>
        ";
        $mensajeApp = "Tu saldo pendiente total es de $" . number_format($deuda, 2, ',', '.') . " ARS. Por favor ingresá a la sección de Pagos o toca el siguiente enlace para abonar con MercadoPago: https://buscotec.com.ar/pagos.html";

    } else {
        // NO TIENE DEUDA
        $texto_creditos = "";
        if ($creditos > 0) {
            $texto_creditos = "
            <div style='background:#e7f1ff; color:#0c5460; padding:15px; border-radius:5px; border-left:4px solid #0d6efd;'>
               💸 ¡Tenés <b>$creditos créditos (casos gratis)</b> disponibles! Para tus próximas operaciones en BuscoTec.
            </div>";
        }

        $mensajeHTML = "
            <h2>Estado de tu cuenta</h2>
            <p style='font-size: 1.1rem;'>Tu saldo pendiente total es de: <b>$0.00 ARS</b>. ¡Estás al día!</p>
            $texto_creditos
            <p style='margin-top:15px;'>¡Gracias por usar BuscoTec!</p>
        ";
        $mensajeApp = "Tu saldo pendiente total es de $0.00 ARS. Estás al día.";
        if ($creditos > 0) {
            $mensajeApp .= " Además tenés $creditos casos gratis para aprovechar.";
        }
    }

    if (!empty($mensajeExtra)) {
        $mensajeHTML .= "
            <div style='background: #eef2f3; border-left: 4px solid #17a2b8; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                <p style='margin: 0; color: #333;'><strong>Mensaje de la Administración:</strong><br> " . nl2br(htmlspecialchars($mensajeExtra)) . "</p>
            </div>
        ";
        $mensajeApp .= "\n\nMensaje Admin: " . $mensajeExtra;
    }

    if ($preview) {
        $preview_data[] = [
            'id' => $prof_id,
            'nombre' => $nombre,
            'email' => $email,
            'deuda' => $deuda,
            'casos' => $casos_sin_pagar,
            'creditos' => $creditos
        ];
        continue;
    }

    if (!empty($mensajeHTML)) {
        // Enviar Email
        $cuerpoHtml = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; border: 1px solid #ddd; padding: 20px; border-radius: 5px;'>
            <h1 style='color: #0d6efd; text-align:center;'>BuscoTec</h1>
            <p>Hola $nombre,</p>
            $mensajeHTML
            <br><hr style='border:none; border-top:1px solid #eee;'>
            <small style='color: #999;'>Este es un mensaje automático del sistema. No respondas a este correo.</small>
        </div>";

        // Enviar Email con control de errores
        try {
            bt_enviar_mail($email, $asunto, $cuerpoHtml);
        } catch (Throwable $e) {
            echo "Error mail $prof_id: " . $e->getMessage() . "\n";
        }

        // Mensaje interno (Buzón)
        try {
            $msgAppSql = "INSERT INTO mensajes (remitente_id, remitente_tipo, destinatario_id, destinatario_tipo, tipo, direccion, mensaje, tiene_adjuntos, leido, estado_respuesta) 
                          VALUES (0, 'sistema', ?, 'profesional', 'sistema', 'sys_to_prof', ?, 0, 0, 'aceptado')";
            $stMsg = $conn->prepare($msgAppSql);
            if ($stMsg) {
                $stMsg->bind_param('is', $prof_id, $mensajeApp);
                $stMsg->execute();
                $stMsg->close();
            } else {
                echo "Error prepare msg $prof_id: " . $conn->error . "\n";
            }
        } catch (Throwable $e) {
            echo "Error DB msg $prof_id: " . $e->getMessage() . "\n";
        }
        
        $enviados++;
        echo "- Enviado a ID $prof_id ($email)\n";
        flush(); // Obligar al servidor a escupir este texto para evitar el error 500 Timeout 
    }
}

if ($preview) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'preview' => $preview_data]);
    exit;
}

echo "Proceso finalizado. Se enviaron $enviados estados de cuenta.\n";
