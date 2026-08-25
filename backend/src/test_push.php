<?php
require_once __DIR__ . '/backend/conexion.php';

// Obtener usuarios con suscripción
$usuarios = [];
$res = $conn->query("SELECT id, nombre, apellido, webpushr_id FROM usuarios WHERE webpushr_id IS NOT NULL AND webpushr_id<>''");
if ($res) $usuarios = $res->fetch_all(MYSQLI_ASSOC);

// Obtener profesionales con suscripción
$profesionales = [];
$res = $conn->query("SELECT id, nombre, apellido, webpushr_id FROM profesionales WHERE webpushr_id IS NOT NULL AND webpushr_id<>''");
if ($res) $profesionales = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Tester Simple Push - Buscotec</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="card shadow">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">📢 Tester Simple de Notificaciones Push</h5>
    </div>
    <div class="card-body">

      <h6>👤 Usuarios</h6>
      <?php if (empty($usuarios)): ?>
        <div class="alert alert-warning">Ningún usuario tiene suscripción guardada.</div>
      <?php else: ?>
        <table class="table table-sm">
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Suscripción</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['nombre']." ".$u['apellido']) ?></td>
              <td><code><?= htmlspecialchars($u['webpushr_id']) ?></code></td>
              <td>
                <form method="POST" action="backend/enviar_notificacion.php" target="_blank">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="rol" value="usuario">
                  <input type="hidden" name="title" value="Buscotec">
                  <input type="hidden" name="body" value="📩 Hola <?= htmlspecialchars($u['nombre']) ?>!">
                  <button type="submit" class="btn btn-sm btn-success">Enviar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <hr>

      <h6>💼 Profesionales</h6>
      <?php if (empty($profesionales)): ?>
        <div class="alert alert-warning">Ningún profesional tiene suscripción guardada.</div>
      <?php else: ?>
        <table class="table table-sm">
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Suscripción</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($profesionales as $p): ?>
            <tr>
              <td><?= $p['id'] ?></td>
              <td><?= htmlspecialchars($p['nombre']." ".$p['apellido']) ?></td>
              <td><code><?= htmlspecialchars($p['webpushr_id']) ?></code></td>
              <td>
                <form method="POST" action="backend/enviar_notificacion.php" target="_blank">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="rol" value="profesional">
                  <input type="hidden" name="title" value="Buscotec">
                  <input type="hidden" name="body" value="📩 Hola <?= htmlspecialchars($p['nombre']) ?>!">
                  <button type="submit" class="btn btn-sm btn-success">Enviar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
