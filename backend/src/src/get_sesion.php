<?php
// backend/get_sesion.php — unificado
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';

echo json_encode([
  'ok' => true,
  'session_id' => session_id(),
  'data' => $_SESSION ?? []
], JSON_UNESCAPED_UNICODE);
