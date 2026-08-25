<?php
// test_form.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Test Notificación - Buscotec</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container">
  <h2 class="mb-4">📩 Probar Notificación Push a un Profesional</h2>

  <form action="enviar_notificacion.php" method="GET" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="id" class="form-label">ID del Profesional</label>
      <input type="number" class="form-control" id="id" name="id" required placeholder="Ej: 1">
      <div class="form-text">El <b>ID</b> del profesional en tu base de datos.</div>
    </div>

    <button type="submit" class="btn btn-primary">Enviar Notificación</button>
  </form>

  <hr class="my-4">

  <p class="text-muted">
    ℹ️ Este formulario envía directamente a <code>enviar_notificacion.php</code>.  
    Verás el resultado en formato JSON en la pantalla.
  </p>
</div>

</body>
</html>
