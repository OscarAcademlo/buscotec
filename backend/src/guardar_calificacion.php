<?php
// ✅ Guarda o actualiza la calificación (solo 1 por caso)
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$casoId     = intval($_POST['caso_id'] ?? 0);
$puntuacion = floatval($_POST['rating'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');
$emisorId   = intval($_POST['emisor_id'] ?? 0);

if ($casoId <= 0 || $puntuacion < 1 || $puntuacion > 5 || $emisorId <= 0) {
  echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
  exit;
}

try {
  // 🔹 Traducir mensaje_id → id real del caso
  $stmtFix = $conn->prepare("SELECT id FROM casos WHERE id = ? OR mensaje_id = ? LIMIT 1");
  $stmtFix->bind_param('ii', $casoId, $casoId);
  $stmtFix->execute();
  $resFix = $stmtFix->get_result();
  if ($rowFix = $resFix->fetch_assoc()) {
    $casoId = intval($rowFix['id']);
  } else {
    echo json_encode(['ok' => false, 'error' => 'Caso no encontrado']);
    exit;
  }

  // 🔹 Obtener datos del caso
  $stmt = $conn->prepare("SELECT solicitante_id, receptor_id, solicitante_tipo, receptor_tipo 
                          FROM casos WHERE id = ?");
  $stmt->bind_param('i', $casoId);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || $res->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'Caso no encontrado']);
    exit;
  }

  $row = $res->fetch_assoc();
  $profesionalId = intval($row['receptor_id']);
  $tipoEmisor = $row['solicitante_tipo'] ?? 'usuario';
  $tipoReceptor = $row['receptor_tipo'] ?? 'profesional';

  // 🔹 Verificar si ya existe calificación para este caso
  $stmtChk = $conn->prepare("SELECT id FROM calificaciones WHERE caso_id = ?");
  $stmtChk->bind_param('i', $casoId);
  $stmtChk->execute();
  $resChk = $stmtChk->get_result();

  if ($rowChk = $resChk->fetch_assoc()) {
    // ✅ Ya existe → actualizar (no duplicar)
    $idExistente = intval($rowChk['id']);
    $stmtUpd = $conn->prepare("UPDATE calificaciones 
                               SET puntuacion = ?, comentario = ?, fecha_creacion = NOW()
                               WHERE id = ?");
    $stmtUpd->bind_param('dsi', $puntuacion, $comentario, $idExistente);
    $stmtUpd->execute();
    $mensaje_final = 'Calificación actualizada.';
  } else {
    // 🔹 Si no existe, insertar nueva
    $stmt2 = $conn->prepare("INSERT INTO calificaciones 
        (caso_id, emisor_id, receptor_id, receptor_profesional_id, tipo_emisor, tipo_receptor, puntuacion, comentario, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt2->bind_param('iiiissds',
        $casoId, $emisorId, $profesionalId, $profesionalId,
        $tipoEmisor, $tipoReceptor, $puntuacion, $comentario);
    $stmt2->execute();
    $mensaje_final = 'Calificación guardada con éxito.';
  }

  // 🔹 NUEVO: Actualizar mensaje de sistema para que desaparezca el link de calificación
  try {
    $msgGracias = "✅ <b>Gracias por calificar su trabajo</b>";
    
    // Usamos un reemplazo que mantenga solo la primera parte del mensaje (antes de la estrella)
    // El SUBSTRING_INDEX ya incluye los <br><br> que estaban antes de la estrella.
    $conn->query("
      UPDATE mensajes 
      SET mensaje = CONCAT(SUBSTRING_INDEX(mensaje, '⭐', 1), '$msgGracias')
      WHERE caso_id = $casoId 
        AND tipo = 'sistema' 
        AND mensaje LIKE '%podés calificar acá%'
    ");
  } catch (Throwable $e) {
    error_log("Error actualizando mensaje de sistema: " . $e->getMessage());
  }

  echo json_encode(['ok' => true, 'msg' => $mensaje_final]);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
