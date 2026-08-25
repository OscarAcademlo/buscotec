<?php
// backend/admin_update_birthdate.php
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Solo administradores
$email = $_SESSION['email'] ?? '';
$ADMIN_ALLOWLIST = ['oscarns@gmail.com', 'orticelli@gmail.com'];
if (!in_array(strtolower($email), $ADMIN_ALLOWLIST)) {
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos de administrador']);
    exit;
}

$id = (int) ($_POST['profesional_id'] ?? 0);
$fecha = $_POST['fecha_nacimiento'] ?? '';

if ($id <= 0 || !$fecha) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

try {
    // Intentar crear la tabla si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS profesionales_datos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      profesional_id INT NOT NULL,
      fecha_nacimiento DATE,
      FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
    )");

    $chk = $conn->prepare("SELECT id FROM profesionales_datos WHERE profesional_id = ? LIMIT 1");
    $chk->bind_param("i", $id);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($exists) {
        $upd = $conn->prepare("UPDATE profesionales_datos SET fecha_nacimiento = ? WHERE profesional_id = ?");
        $upd->bind_param("si", $fecha, $id);
        $upd->execute();
        $upd->close();
    } else {
        $ins = $conn->prepare("INSERT INTO profesionales_datos (profesional_id, fecha_nacimiento) VALUES (?, ?)");
        $ins->bind_param("is", $id, $fecha);
        $ins->execute();
        $ins->close();
    }

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
