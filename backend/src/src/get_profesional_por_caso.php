<?php
// ✅ Detecta si el parámetro es caso.id o mensaje_id, y devuelve los datos del profesional correcto
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$inputId = intval($_GET['caso_id'] ?? 0);

if ($inputId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    // 1️⃣ Buscar el caso correcto (por id o por mensaje_id)
    $sql = "
        SELECT id, receptor_id AS profesional_id
        FROM casos
        WHERE id = ? OR mensaje_id = ?
        ORDER BY id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $inputId, $inputId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'Caso no encontrado']);
        exit;
    }

    $caso = $res->fetch_assoc();
    $profesionalId = intval($caso['profesional_id']);

    // 2️⃣ Buscar datos del profesional
    $sql2 = "SELECT nombre, foto_profesional AS foto
             FROM profesionales
             WHERE id = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('i', $profesionalId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    if ($row = $res2->fetch_assoc()) {
        echo json_encode([
            'ok' => true,
            'nombre' => $row['nombre'],
            'foto' => $row['foto'] ?: 'img/placeholder-pro.jpg'
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Profesional no encontrado']);
    }

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
