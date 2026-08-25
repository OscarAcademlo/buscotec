<?php
// ============================================================
// crear_cuenta_demo.php — Crea la cuenta demo para Apple Review
// BORRAR ESTE ARCHIVO DESPUÉS DE EJECUTARLO
// ============================================================
declare(strict_types=1);

// Credenciales de la cuenta demo a crear
$DEMO_EMAIL    = 'apple@buscotec.com';   // ← CAMBIAR si usaste otro email en App Store Connect
$DEMO_PASSWORD = 'BuscoTec2026';         // ← CAMBIAR por la contraseña que pusiste en App Store Connect
$DEMO_NOMBRE   = 'Apple Reviewer';
$DEMO_APELLIDO = 'Demo';

// Clave de seguridad para no ejecutar por accidente
$KEY = $_GET['key'] ?? '';
if ($KEY !== 'setup2026') {
    die('Acceso denegado. Usar ?key=setup2026');
}

require_once __DIR__ . '/conexion.php';

if (!$conn) {
    die(json_encode(['ok' => false, 'error' => 'Sin conexión a DB']));
}

header('Content-Type: application/json; charset=utf-8');

$results = [];
$hash = password_hash($DEMO_PASSWORD, PASSWORD_BCRYPT);

// 1. Crear o actualizar usuario demo
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $DEMO_EMAIL);
$stmt->execute();
$res = $stmt->get_result();
$existing = $res->fetch_assoc();
$stmt->close();

if ($existing) {
    // Actualizar contraseña
    $stmt2 = $conn->prepare("UPDATE usuarios SET password = ?, nombre = ?, apellido = ? WHERE email = ?");
    $stmt2->bind_param('ssss', $hash, $DEMO_NOMBRE, $DEMO_APELLIDO, $DEMO_EMAIL);
    $stmt2->execute();
    $stmt2->close();
    $results[] = ['tabla' => 'usuarios', 'accion' => 'actualizado', 'id' => $existing['id']];
} else {
    $stmt2 = $conn->prepare("INSERT INTO usuarios (nombre, apellido, email, password, estado) VALUES (?, ?, ?, ?, 'activo')");
    $stmt2->bind_param('ssss', $DEMO_NOMBRE, $DEMO_APELLIDO, $DEMO_EMAIL, $hash);
    $stmt2->execute();
    $newId = $conn->insert_id;
    $stmt2->close();
    $results[] = ['tabla' => 'usuarios', 'accion' => 'creado', 'id' => $newId];
}

// 2. Verificar que el login funciona con las credenciales demo
$stmt3 = $conn->prepare("SELECT id, nombre, password FROM usuarios WHERE email = ? LIMIT 1");
$stmt3->bind_param('s', $DEMO_EMAIL);
$stmt3->execute();
$res3 = $stmt3->get_result();
$userRow = $res3->fetch_assoc();
$stmt3->close();

$loginOk = $userRow && password_verify($DEMO_PASSWORD, $userRow['password']);

echo json_encode([
    'ok'         => true,
    'demo_email' => $DEMO_EMAIL,
    'demo_pass'  => $DEMO_PASSWORD,
    'login_test' => $loginOk ? 'FUNCIONA ✅' : 'FALLA ❌',
    'results'    => $results,
    'instruccion' => 'BORRAR ESTE ARCHIVO AHORA: rm crear_cuenta_demo.php',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
