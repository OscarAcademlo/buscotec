<?php
require_once __DIR__ . '/mailer.php';

$to = 'oscarns@gmail.com'; // <-- cambiá por tu correo de prueba
$subject = 'Prueba Buscotec - PHPMailer';
$html = '<h3>Prueba de email</h3><p>Si recibís esto, la configuración funciona.</p>';

$ok = bt_enviar_mail($to, $subject, $html);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Test email</title></head>
<body>
<?php if ($ok): ?>
  <div style="color:green">✅ Email enviado. Revisá bandeja y spam.</div>
<?php else: ?>
  <div style="color:red">❌ No se pudo enviar. Ver logs en backend/logs/php_errors.log</div>
<?php endif; ?>
</body>
</html>
