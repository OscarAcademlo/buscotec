<?php
// ============================================================
// backend/test_webpushr.php — Verificar estado de Webpushr
// ============================================================
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión a base de datos']);
    exit;
}

$conn->set_charset('utf8mb4');

$resultado = [
    'ok' => true,
    'diagnostico' => []
];

// 1) Usuarios con webpushr_id
$usuariosConWebpushr = [];
$res = $conn->query("SELECT id, nombre, apellido, email, webpushr_id FROM usuarios WHERE webpushr_id IS NOT NULL AND webpushr_id <> '' ORDER BY id DESC LIMIT 20");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $usuariosConWebpushr[] = [
            'id' => $row['id'],
            'nombre' => trim($row['nombre'] . ' ' . $row['apellido']),
            'email' => $row['email'],
            'webpushr_id' => $row['webpushr_id']
        ];
    }
}
$resultado['diagnostico']['usuarios_con_webpushr'] = $usuariosConWebpushr;
$resultado['diagnostico']['total_usuarios_con_webpushr'] = count($usuariosConWebpushr);

// 2) Profesionales con webpushr_id
$profesionalesConWebpushr = [];
$res2 = $conn->query("SELECT id, nombre, apellido, email, webpushr_id FROM profesionales WHERE webpushr_id IS NOT NULL AND webpushr_id <> '' ORDER BY id DESC LIMIT 20");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $profesionalesConWebpushr[] = [
            'id' => $row['id'],
            'nombre' => trim($row['nombre'] . ' ' . ($row['apellido'] ?? '')),
            'email' => $row['email'],
            'webpushr_id' => $row['webpushr_id']
        ];
    }
}
$resultado['diagnostico']['profesionales_con_webpushr'] = $profesionalesConWebpushr;
$resultado['diagnostico']['total_profesionales_con_webpushr'] = count($profesionalesConWebpushr);

// 3) Total usuarios y profesionales
$totalUsuarios = 0;
$totalProfesionales = 0;
$resTotalU = $conn->query("SELECT COUNT(*) as total FROM usuarios");
if ($resTotalU) {
    $totalUsuarios = (int) $resTotalU->fetch_assoc()['total'];
}
$resTotalP = $conn->query("SELECT COUNT(*) as total FROM profesionales");
if ($resTotalP) {
    $totalProfesionales = (int) $resTotalP->fetch_assoc()['total'];
}
$resultado['diagnostico']['total_usuarios'] = $totalUsuarios;
$resultado['diagnostico']['total_profesionales'] = $totalProfesionales;

// 4) Test de envío de push (opcional, si se pasa ?test_push_to=webpushr_id)
if (!empty($_GET['test_push_to'])) {
    $sid = trim($_GET['test_push_to']);

    $payload = [
        'title' => '🔔 Test de notificación BuscoTec',
        'message' => 'Esta es una prueba de push. Si ves esto, Webpushr funciona correctamente.',
        'target_url' => 'https://buscotec.com.ar/',
        'sid' => $sid
    ];

    $ch = curl_init('https://api.webpushr.com/v1/notification/send/sid');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'webpushrKey: 4d4a588e817b482187cee2c9dfb64aec',
        'webpushrAuthToken: 114560'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resultado['test_push'] = [
        'sent_to' => $sid,
        'http_code' => $httpCode,
        'response' => json_decode($resp, true) ?? $resp
    ];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
