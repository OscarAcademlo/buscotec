<?php
// ==========================================
// sincronizar_mensajes_idglobal.php
// Unifica los mensajes existentes con su id_global
// ==========================================

declare(strict_types=1);
require_once __DIR__ . '/conexion.php';

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.log');

echo "=== SINCRONIZACIÓN DE MENSAJES (Buscotec) ===\n\n";

// 1️⃣ Validar conexión
if (!($conn instanceof mysqli)) {
    echo "❌ No hay conexión con la base de datos.\n";
    exit;
}
$conn->set_charset('utf8mb4');

// 2️⃣ Verificar columna id_global en mensajes
$conn->query("ALTER TABLE mensajes ADD COLUMN IF NOT EXISTS id_global VARCHAR(64) NULL");
echo "✅ Columna 'id_global' verificada/creada.\n";

// 3️⃣ Seleccionar mensajes sin id_global
$sql = "SELECT id, remitente_id, remitente_tipo, destinatario_id, destinatario_tipo 
        FROM mensajes
        WHERE id_global IS NULL OR id_global = ''";

$res = $conn->query($sql);
$total = $res->num_rows;
if ($total === 0) {
    echo "✅ No hay mensajes pendientes de sincronizar.\n";
    exit;
}

echo "📬 Se encontraron {$total} mensajes sin id_global.\n\n";

$actualizados = 0;

while ($msg = $res->fetch_assoc()) {
    $msg_id = (int)$msg['id'];
    $id_global = null;

    // Buscar email del remitente según tipo
    if ($msg['remitente_tipo'] === 'usuario') {
        $st = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
    } else {
        $st = $conn->prepare("SELECT email FROM profesionales WHERE id = ?");
    }

    $st->bind_param("i", $msg['remitente_id']);
    $st->execute();
    $r = $st->get_result();
    $email = $r->fetch_column() ?: null;
    $st->close();

    if ($email) {
        // Buscar id_global asociado
        $st = $conn->prepare("
            SELECT id_global FROM usuarios WHERE email = ?
            UNION
            SELECT id_global FROM profesionales WHERE email = ?
            LIMIT 1
        ");
        $st->bind_param("ss", $email, $email);
        $st->execute();
        $r = $st->get_result();
        if ($row = $r->fetch_assoc()) {
            $id_global = $row['id_global'];
        }
        $st->close();
    }

    // Si encontramos el id_global, actualizar mensaje
    if ($id_global) {
        $up = $conn->prepare("UPDATE mensajes SET id_global = ? WHERE id = ?");
        $up->bind_param("si", $id_global, $msg_id);
        $up->execute();
        $up->close();
        $actualizados++;
        echo "✅ Mensaje ID {$msg_id} sincronizado con id_global {$id_global}\n";
    } else {
        echo "⚠️ No se encontró id_global para mensaje {$msg_id} (email: {$email})\n";
    }
}

echo "\n---\nProceso finalizado. {$actualizados} mensajes sincronizados de {$total}.\n";
echo "✅ Listo. Ya podés borrar este archivo o renombrarlo por seguridad.\n";
