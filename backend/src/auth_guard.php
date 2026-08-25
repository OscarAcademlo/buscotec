<?php
require_once __DIR__.'/session_boot.php';
require_once __DIR__.'/db.php';

// Si hay sesión PHP → listo
if (!empty($_SESSION['user_id'])) {
  return;
}

// Si NO hay sesión PHP → intentar token
if (!empty($_COOKIE['BUSCOTEC_AUTH'])) {
  $hash = hash('sha256', $_COOKIE['BUSCOTEC_AUTH']);

  // Buscar token en DB y que no esté vencido
  // SELECT user_id FROM auth_tokens WHERE token_hash = ? AND expires_at > NOW()

  if ($userId) {
    $_SESSION['user_id'] = $userId;
    return;
  }
}

// Nada funcionó → no autenticado
http_response_code(401);
echo json_encode(['ok'=>false,'error'=>'No autenticado']);
exit;
