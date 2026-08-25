<?php
declare(strict_types=1);

/* =========================
   SESIÓN UNIFICADA
========================= */
require_once __DIR__ . '/session_boot.php';

/* =========================
   FALLBACK DE ROLES

if (!empty($_SESSION['id']) && empty($_SESSION['roles'])) {
    $_SESSION['roles'] = [];
    $_SESSION['role_ids'] = [];

    try {
        require_once __DIR__ . '/conexion.php';
        $uid = (int)$_SESSION['id'];

        $q1 = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
        $q1->bind_param('i', $uid);
        $q1->execute();

        if ($q1->get_result()->num_rows > 0) {
            $_SESSION['roles'][] = 'usuario';
            $_SESSION['role'] = 'usuario';
            $_SESSION['role_ids']['usuario'] = $uid;
        } else {
            $q2 = $conn->prepare("SELECT id FROM profesionales WHERE id = ?");
            $q2->bind_param('i', $uid);
            $q2->execute();

            if ($q2->get_result()->num_rows > 0) {
                $_SESSION['roles'][] = 'profesional';
                $_SESSION['role'] = 'profesional';
                $_SESSION['role_ids']['profesional'] = $uid;
            }
        }
    } catch (Throwable $e) {
        error_log('BOOT ERROR: ' . $e->getMessage());
    }
}

⚠️ CLAVE:
   NO echo
   NO json
   NO exit
*/
