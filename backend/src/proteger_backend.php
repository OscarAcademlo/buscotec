<?php
// ============================================================
// proteger_backend.php — protege todo el backend
// ============================================================

require_once __DIR__ . '/session_boot.php';

// ⚙️ Cambiá este correo por el del admin autorizado
$ADMIN_EMAIL = 'oscarns@gmail.com';

// ⚙️ Si no hay sesión o no es el admin, bloquear acceso
if (!isset($_SESSION['email']) || $_SESSION['email'] !== $ADMIN_EMAIL) {
  http_response_code(403);
  echo "<h2 style='text-align:center;margin-top:100px;color:red;'>
            🚫 Acceso denegado<br>Solo el administrador puede entrar aquí
          </h2>";
  exit;
}
