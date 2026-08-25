<?php
require_once __DIR__ . '/conexion.php';

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (!$email || !$token) {
    $msg = "Solicitud inválida.";
} else {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email=? AND codigo_verificacion=? LIMIT 1");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {

        $up = $conn->prepare("UPDATE usuarios 
                              SET verificado=1, codigo_verificacion=NULL 
                              WHERE email=?");
        $up->bind_param("s", $email);
        $up->execute();

        $msg = "¡Tu cuenta ha sido verificada!";
    } else {
        $msg = "El enlace no es válido o ya fue usado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cuenta verificada</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background: #1877f2;
    color: #fff;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    text-align:center;
}
.card {
    background:#fff;
    color:#000;
    border-radius:12px;
    padding:30px;
    max-width:500px;
}
.btn-primary {
    background:#1877f2;
    border:none;
}
</style>
</head>
<body>

<div class="card shadow">
  <h2 class="mb-3">BuscoTec</h2>
  <p class="lead"><?php echo $msg; ?></p>
  <a href="../index.html" class="btn btn-primary mt-3">Ir al inicio</a>
</div>

</body>
</html>
