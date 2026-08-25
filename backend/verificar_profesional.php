<?php
// ============================================================
// backend/verificar_profesional.php — versión estable y segura (2025)
// ============================================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

require_once __DIR__ . '/conexion.php';

if (!$conn instanceof mysqli) {
  die('<h2 style="color:red;text-align:center;">❌ Error de conexión a la base de datos.</h2>');
}

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de cuenta - BuscoTec</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="card shadow-sm p-4 text-center" style="max-width:500px;">

<?php
if ($email === '' || $token === '') {
  echo "<h3 style='color:red;'>❌ Enlace inválido</h3>
        <p>El enlace de verificación no es correcto o está incompleto.</p>";
} else {
  try {
    $st = $conn->prepare("
      SELECT id, nombre, email_verificado, verificado 
      FROM profesionales 
      WHERE email=? AND id_global=? 
      LIMIT 1
    ");
    $st->bind_param('ss', $email, $token);
    $st->execute();
    $res = $st->get_result();
    $pro = $res->fetch_assoc();

    if (!$pro) {
      echo "<h4 style='color:red;'>❌ Profesional no encontrado</h4>
            <p>El enlace no coincide con ningún registro en nuestra base de datos.</p>";
    } elseif ((int)$pro['email_verificado'] === 1 || (int)$pro['verificado'] === 1) {
      echo "<h4 style='color:green;'>✅ Tu cuenta ya estaba verificada</h4>
            <p>Podés iniciar sesión cuando quieras.</p>";
    } else {
      // Marcar como verificado
      $upd = $conn->prepare("
        UPDATE profesionales 
        SET email_verificado=1, verificado=1, fecha_verificacion=NOW() 
        WHERE id=?
      ");
      $upd->bind_param('i', $pro['id']);
      $upd->execute();

      echo "<h3 style='color:green;'>✅ ¡Cuenta verificada correctamente!</h3>
            <p>Gracias <b>" . htmlspecialchars($pro['nombre']) . "</b>, tu cuenta ya está activa y visible en el mapa.</p>";
    }
  } catch (Throwable $e) {
    echo "<h4 style='color:red;'>❌ Error interno</h4>
          <p>Por favor, intentá nuevamente más tarde.</p>";
    error_log('[VERIFICAR_PROFESIONAL] ' . $e->getMessage());
  }
}
?>

    <a href='/login.html' class='btn btn-primary mt-3'>
      <i class='bi bi-box-arrow-in-right'></i> Ir al login
    </a>
    <hr>
    <p class='text-muted mb-0'>BuscoTec © 2025</p>
  </div>

  <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
