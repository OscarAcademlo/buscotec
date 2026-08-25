<?php
// ✅ backend/check_caso_aceptado.php — devuelve el ID correcto de la tabla "casos"
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// 1️⃣ Leer el usuario actual
$userId = intval($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    // 2️⃣ Buscar el último caso ACEPTADO por un profesional
    $sql = "SELECT c.id AS caso_id, p.nombre, p.apellido
            FROM casos c
            INNER JOIN profesionales p ON c.receptor_id = p.id
            WHERE c.solicitante_id = ?
              AND c.aceptado_por = 'profesional'
            ORDER BY c.id DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    // 3️⃣ Si encontró un caso, devuelve su ID real (de la tabla casos)
    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'ok' => true,
            'profesional' => trim($row['nombre'] . ' ' . $row['apellido']),
            'caso_id' => intval($row['caso_id'])
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Sin casos aceptados']);
    }

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
