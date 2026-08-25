<?php
declare(strict_types=1);

/* =========================
   SESIÓN UNIFICADA
========================= */
require_once __DIR__ . '/session_boot.php';

/* =========================
   FALLBACK PARA APP MÓVIL (Headers)
   Si la sesión está vacía, intentamos recuperar el usuario de los headers.
========================= */
if (empty($_SESSION['id'])) {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_UPPER);
    } else {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }

    $headerUid = $headers['X-USER-ID'] ?? $_SERVER['HTTP_X_USER_ID'] ?? '';
    $headerRole = $headers['X-USER-ROLE'] ?? $_SERVER['HTTP_X_USER_ROLE'] ?? '';

    if (!empty($headerUid)) {
        $_SESSION['id']   = (int) $headerUid;
        $_SESSION['role'] = trim($headerRole ?: 'usuario');
    } elseif (!empty($_POST['_uid'])) {
        $_SESSION['id']   = (int) $_POST['_uid'];
        $_SESSION['role'] = trim($_POST['_role'] ?? 'usuario');
    }
}

/* =========================
   FALLBACK DE ROLES (Opcional si ya vinieron en headers)
========================= */
if (!empty($_SESSION['id']) && empty($_SESSION['role_ids'])) {
    $_SESSION['role_ids'] = [($_SESSION['role'] ?? 'usuario') => (int)$_SESSION['id']];
}

/* =========================
   EVITAR OUTPUT ANTES DE TIEMPO
========================= */
// NO echo
// NO json
// NO exit
?>
