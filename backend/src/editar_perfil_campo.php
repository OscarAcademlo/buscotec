<?php
// Reportar ERRORES para depuración profunda
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Intentar cargar config.php (buscando en el mismo nivel o uno arriba por las dudas)
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    echo json_encode(['ok' => false, 'error' => 'No se encontró config.php en el servidor']);
    exit;
}

$id = $_POST['id'] ?? null;
$role = $_POST['role'] ?? 'usuario';
$campo = $_POST['campo'] ?? '';
$valor = $_POST['valor'] ?? '';

if (!$id || !$campo) {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos (id o campo)']);
    exit;
}

// Mapeo EXACTO basado en estructura BD
$allowedFields = [];
if ($role === 'profesional') {
    $allowedFields = [
        'nombre' => 'nombre',
        'apellido' => 'apellido',
        'telefono' => 'whatsapp',
        'whatsapp' => 'whatsapp',
        'email' => 'email',
        'descripcion' => 'descripcion',
        'password' => 'password'
    ];
} else {
    $allowedFields = [
        'nombre' => 'nombre',
        'apellido' => 'apellido',
        'email' => 'email',
        'telefono' => 'telefono',
        'password' => 'clave'
    ];
}

if (!array_key_exists($campo, $allowedFields)) {
    echo json_encode(['ok' => false, 'error' => 'Campo no permitido: ' . $campo]);
    exit;
}

$dbField = $allowedFields[$campo];
$table = ($role === 'profesional') ? 'profesionales' : 'usuarios';

// Tratamiento password
if ($campo === 'password') {
    $valor = password_hash($valor, PASSWORD_DEFAULT);
}

// Update seguro
$sql = "UPDATE $table SET $dbField = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Error SQL Prepare: ' . $conn->error]);
    exit;
}

$stmt->bind_param("si", $valor, $id);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'message' => 'Actualizado correctamente']);
} else {
    echo json_encode(['ok' => false, 'error' => 'Error BD Execute: ' . $stmt->error]);
}
?>