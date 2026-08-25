<?php
// backend/unificar_id_global.php
// 🔧 Ejecutar UNA sola vez para unificar todos los id_global de usuarios y profesionales

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/conexion.php';
header('Content-Type: text/plain; charset=utf-8');

if (!($conn instanceof mysqli)) {
    die("❌ Error: No hay conexión a la base de datos.\n");
}

$conn->set_charset('utf8mb4');

echo "=== UNIFICACIÓN DE ID_GLOBAL (Buscotec) ===\n\n";

// Paso 1: agregar las columnas si no existen
try {
    $conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS id_global VARCHAR(50) DEFAULT NULL AFTER id;");
    $conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS id_global VARCHAR(50) DEFAULT NULL AFTER id;");
    echo "✅ Columnas id_global verificadas/creadas.\n";
} catch (Throwable $e) {
    echo "⚠️ No se pudieron agregar columnas (quizá ya existen): " . $e->getMessage() . "\n";
}

// Paso 2: asignar hash por email donde esté vacío
$conn->query("UPDATE usuarios SET id_global = MD5(LOWER(TRIM(email))) WHERE (id_global IS NULL OR id_global = '');");
$conn->query("UPDATE profesionales SET id_global = MD5(LOWER(TRIM(email))) WHERE (id_global IS NULL OR id_global = '');");
echo "✅ Se asignaron hashes únicos a todos los emails.\n";

// Paso 3: unificar por email coincidente (usuarios y profesionales)
$sql = "
    SELECT u.email, u.id_global AS user_global, p.id_global AS pro_global
    FROM usuarios u
    JOIN profesionales p ON u.email = p.email
";
$res = $conn->query($sql);

$updates = 0;
while ($row = $res->fetch_assoc()) {
    $email = $row['email'];
    $global = $row['user_global'] ?: $row['pro_global'];
    if ($global) {
        $st1 = $conn->prepare("UPDATE usuarios SET id_global = ? WHERE email = ?");
        $st1->bind_param('ss', $global, $email);
        $st1->execute();
        $st1->close();

        $st2 = $conn->prepare("UPDATE profesionales SET id_global = ? WHERE email = ?");
        $st2->bind_param('ss', $global, $email);
        $st2->execute();
        $st2->close();

        $updates++;
    }
}
echo "✅ Unificados {$updates} pares usuario/profesional con el mismo email.\n";

// Paso 4: Mostrar un resumen
echo "\n--- MUESTRA FINAL ---\n";
$sqlFinal = "
    SELECT u.email, u.id_global AS user_id_global, p.id_global AS pro_id_global
    FROM usuarios u
    JOIN profesionales p ON u.email = p.email
";
$res2 = $conn->query($sqlFinal);
while ($r = $res2->fetch_assoc()) {
    echo "{$r['email']} => {$r['user_id_global']} | {$r['pro_id_global']}\n";
}

echo "\n✅ Proceso finalizado con éxito.\n";
