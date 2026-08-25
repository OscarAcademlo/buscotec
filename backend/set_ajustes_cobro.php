<?php
declare(strict_types=1);
require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión']);
    exit;
}
$conn->set_charset('utf8mb4');

$creditos = (int)($_POST['creditos_bienvenida'] ?? 5);
$dia_cobro = trim($_POST['dia_cobro'] ?? 'Lunes');
$hora_cobro = trim($_POST['hora_cobro'] ?? '10');
$mensaje_extra = trim($_POST['mensaje_extra'] ?? '');
$cron_activado = isset($_POST['cron_activado']) ? (int)$_POST['cron_activado'] : 1;


if ($creditos < 0 || $creditos > 100) {
    echo json_encode(['ok' => false, 'error' => 'Créditos deben ser entre 0 y 100']);
    exit;
}

$stmt_cre = $conn->prepare("INSERT INTO ajustes (clave, valor) VALUES ('creditos_bienvenida', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
$stmt_cre->bind_param('s', $creditos);
$stmt_cre->execute();
$stmt_cre->close();

$stmt_dia = $conn->prepare("INSERT INTO ajustes (clave, valor) VALUES ('dia_cobro', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
$stmt_dia->bind_param('s', $dia_cobro);
$stmt_dia->execute();
$stmt_dia->close();

$stmt_hora = $conn->prepare("INSERT INTO ajustes (clave, valor) VALUES ('hora_cobro', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
$stmt_hora->bind_param('s', $hora_cobro);
$stmt_hora->execute();
$stmt_hora->close();

$stmt_msg = $conn->prepare("INSERT INTO ajustes (clave, valor) VALUES ('mensaje_extra', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
$stmt_msg->bind_param('s', $mensaje_extra);
$stmt_msg->execute();
$stmt_msg->close();

$stmt_act = $conn->prepare("INSERT INTO ajustes (clave, valor) VALUES ('cron_activado', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
$stmt_act->bind_param('i', $cron_activado);
$stmt_act->execute();
$stmt_act->close();

echo json_encode(['ok' => true]);


?>
