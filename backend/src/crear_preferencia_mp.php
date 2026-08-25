<?php
// webyapp/public_html/backend/crear_preferencia_mp.php
// Genera una preferencia de pago en Mercado Pago

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/session_boot.php';

$config = require __DIR__ . '/config/mercadopago.php';
$access_token = $config['access_token'];

// 1. Validar ID de caso y sesión
$id_caso = isset($_POST['id_caso']) ? (int)$_POST['id_caso'] : 0;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Sesión no iniciada']);
    exit;
}

// 2. Obtener monto y detalles del caso
$monto = 0;
$titulo = "Pago de servicio - BuscoTec";

if ($id_caso > 0) {
    // Pago individual
    $q = $conn->prepare("SELECT cargo_usd FROM casos WHERE id = ? AND receptor_id = ? AND pagado = 0 LIMIT 1");
    $q->bind_param('ii', $id_caso, $user_id);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    if (!$res) {
        echo json_encode(['ok' => false, 'error' => 'Caso no encontrado o ya pagado']);
        exit;
    }
    // Valor por defecto si cargo_usd es 0 o muy bajo (como en el dashboard)
    $monto = (float)$res['cargo_usd'];
    if ($monto < 50) {
        $resA = $conn->query("SELECT valor FROM ajustes WHERE clave='valor_caso_usd' LIMIT 1");
        $monto = ($resA && $row = $resA->fetch_assoc()) ? (float)$row['valor'] : 1400.00;
    }
    $titulo = "Pago Caso #$id_caso - BuscoTec";
} else {
    // Pago total (si id_caso es 0)
    // Aquí podrías implementar la suma de todos los pendientes, pero para empezar usaremos el total enviado por el frontend o recalculado acá.
    // Calculemos el total pendiente
    $q = $conn->prepare("SELECT cargo_usd FROM casos WHERE receptor_id = ? AND pagado = 0");
    $q->bind_param('i', $user_id);
    $q->execute();
    $res = $q->get_result();
    $total = 0;
    while($row = $res->fetch_assoc()) {
        $c = (float)$row['cargo_usd'];
        if ($c < 50) {
             $resA = $conn->query("SELECT valor FROM ajustes WHERE clave='valor_caso_usd' LIMIT 1");
             $c = ($resA && $rowA = $resA->fetch_assoc()) ? (float)$rowA['valor'] : 1400.00;
        }
        $total += $c;
    }
    if ($total <= 0) {
        echo json_encode(['ok' => false, 'error' => 'No tenés saldo pendiente']);
        exit;
    }
    $monto = $total;
    $titulo = "Pago Saldo Total - BuscoTec";
}

// 3. Crear preferencia en Mercado Pago vía cURL
$url = "https://api.mercadopago.com/checkout/preferences";

$data = [
    "items" => [
        [
            "title"       => $titulo,
            "quantity"    => 1,
            "unit_price"  => $monto,
            "currency_id" => "ARS"
        ]
    ],
    "external_reference" => ($id_caso > 0 ? "CASO_" . $id_caso : "TOTAL_" . $user_id),
    "back_urls" => [
        "success" => "https://www.buscotec.com.ar/pagos.html?status=success",
        "failure" => "https://www.buscotec.com.ar/pagos.html?status=failure",
        "pending" => "https://www.buscotec.com.ar/pagos.html?status=pending"
    ],
    "auto_return" => "approved",
    "notification_url" => "https://www.buscotec.com.ar/backend/notificacion_mp.php"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$res_mp = json_decode($response, true);

if ($http_code === 200 || $http_code === 201) {
    echo json_encode([
        'ok' => true,
        'init_point' => $res_mp['init_point'] // Este es el link al que debe ir el usuario
    ]);
} else {
    echo json_encode([
        'ok' => false, 
        'error' => 'Error al crear preferencia en MP',
        'details' => $res_mp
    ]);
}
