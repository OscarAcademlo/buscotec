<?php
// backend/check_session.php - Diagnóstico (V3 Simplificada)
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Forzamos la inclusión directa. Estamos en /backend, el boot también.
$bootPath = __DIR__ . '/session_boot.php';

if (!file_exists($bootPath)) {
    die("<h1>Error Fatal</h1><p>No encuentro el archivo de arranque en: <br><code>$bootPath</code></p>");
}

require_once $bootPath;

// Contador de sesión
if (!isset($_SESSION['debug_counter'])) {
    $_SESSION['debug_counter'] = 0;
}
$_SESSION['debug_counter']++;

// Salida
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Sesión</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            line-height: 1.5;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        .fail {
            color: red;
            font-weight: bold;
        }

        .box {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
    </style>
</head>

<body>
    <h1>Diagnóstico de Sesión (Backend)</h1>

    <div class="box">
        <p><strong>Estado del Contador:</strong> <span
                style="font-size: 2em;"><?php echo $_SESSION['debug_counter']; ?></span></p>
        <p>
            <?php if ($_SESSION['debug_counter'] > 1): ?>
                <span class="success">✅ LA SESIÓN ESTÁ FUNCIONANDO (El contador sube).</span>
            <?php else: ?>
                <span class="fail">⚠️ PRIMERA VEZ O SESIÓN REINICIADA. Refresca la página. Si siegue en 1, la sesión no
                    persiste.</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="box">
        <h3>Información Técnica</h3>
        <ul>
            <li><strong>Session ID:</strong> <?php echo session_id(); ?></li>
            <li><strong>Carpeta de guardado:</strong> <?php echo session_save_path(); ?></li>
            <li><strong>Permisos carpeta:</strong>
                <?php echo substr(sprintf('%o', fileperms(session_save_path())), -4); ?></li>
            <li><strong>Cookie Domain:</strong> <?php echo session_get_cookie_params()['domain'] ?? 'null (actual)'; ?>
            </li>
            <li><strong>Cookie Secure:</strong> <?php echo session_get_cookie_params()['secure'] ? 'Sí' : 'No'; ?></li>
        </ul>
    </div>

    <div class="box">
        <h3>Datos almacenados:</h3>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

</body>

</html>