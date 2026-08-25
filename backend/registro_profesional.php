<?php
// ============================================================
// registro_profesional.php — versión mejorada BuscoTec (2025)
// Compatible con categorías múltiples (checkbox) + verificación por email
// ============================================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/conexion.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/src/Exception.php';

// ✅ Comprobación de conexión
if (!$conn || $conn->connect_errno) {
  http_response_code(500);
  echo json_encode(["ok" => false, "msg" => "Error de conexión a la base de datos"]);
  exit;
}

// ✅ Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["ok" => false, "msg" => "Método no permitido"]);
  exit;
}

// === Datos del formulario ===
$nombre       = trim($_POST['nombre'] ?? '');
$apellido     = trim($_POST['apellido'] ?? '');
$email        = trim($_POST['email'] ?? '');
$password_raw = trim($_POST['password'] ?? '');
$whatsapp     = trim($_POST['whatsapp'] ?? '');
$experiencia  = (int)($_POST['experiencia'] ?? 0);
$descripcion  = trim($_POST['descripcion'] ?? '');
$direccion    = trim($_POST['direccion'] ?? '');
$barrio       = trim($_POST['barrio'] ?? '');
$ciudad       = trim($_POST['ciudad'] ?? '');
$provincia    = trim($_POST['provincia'] ?? '');
$acepta_terminos = isset($_POST['acepta_terminos']) ? 1 : 0;
$categorias   = $_POST['categorias'] ?? []; // array de checkbox marcados
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

// === Validaciones ===
if ($nombre === '' || $apellido === '' || !$email || !$password_raw || !$whatsapp) {
  echo json_encode(["ok" => false, "campo" => "general", "msg" => "Completa todos los campos obligatorios"]);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(["ok" => false, "campo" => "email", "msg" => "Email inválido"]);
  exit;
}
if (strlen($password_raw) < 8) {
  echo json_encode(["ok" => false, "campo" => "password", "msg" => "La contraseña debe tener al menos 8 caracteres"]);
  exit;
}
if (empty($categorias)) {
  echo json_encode(["ok" => false, "campo" => "categorias", "msg" => "Seleccioná al menos una profesión"]);
  exit;
}
if (!$acepta_terminos) {
  echo json_encode(["ok" => false, "campo" => "acepta_terminos", "msg" => "Debes aceptar los términos"]);
  exit;
}

// ✅ Validar imágenes obligatorias
if (empty($_FILES['foto_profesional']['name']) || empty($_FILES['dni_frente']['name']) || empty($_FILES['dni_dorso']['name'])) {
  echo json_encode(["ok" => false, "campo" => "archivos", "msg" => "Faltan imágenes obligatorias (foto profesional o DNI)."]);
  exit;
}

$password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

// === Duplicados ===
$chk = $conn->prepare("SELECT id FROM profesionales WHERE email=? OR whatsapp=? LIMIT 1");
$chk->bind_param("ss", $email, $whatsapp);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
  echo json_encode(["ok" => false, "campo" => "duplicado", "msg" => "Correo o WhatsApp ya registrados"]);
  exit;
}
$chk->close();

// === Función para guardar imágenes ===
function guardar_imagen($campo, $carpeta, $nombreBase) {
  if (empty($_FILES[$campo]['name'])) return '';
  $dir = __DIR__ . '/../img/' . $carpeta . '/';
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION) ?: 'jpg');
  $archivo = preg_replace('/\s+/', '_', $nombreBase) . '_' . $campo . '.' . $ext;
  $destino = $dir . $archivo;
  if (move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
    return 'img/' . $carpeta . '/' . $archivo;
  }
  return '';
}

// === Guardar imágenes ===
$base = strtolower(preg_replace('/\s+/', '_', $nombre . '_' . $apellido));
$foto_profesional = guardar_imagen('foto_profesional', 'profesionales', $base);
$foto_matricula   = guardar_imagen('foto_matricula', 'matriculas', $base);
$foto_dni_frente  = guardar_imagen('dni_frente', 'dni', $base);
$foto_dni_dorso   = guardar_imagen('dni_dorso', 'dni', $base);
$cert_penales     = guardar_imagen('cert_penales', 'penales', $base);

$id_global = md5(uniqid(rand(), true));
$fecha = date('Y-m-d H:i:s');
$rating = 0;
$email_verificado = 0;
$verificado = 0;

// === Traer créditos de bienvenida de configuración (default 5) ===
$creditos_bienvenida = 5;
$qCred = $conn->query("SELECT valor FROM ajustes WHERE clave='creditos_bienvenida'");
if ($qCred && $row = $qCred->fetch_assoc()) {
    $creditos_bienvenida = (int)$row['valor'];
}

// Asegurarse de que la columna exista para evitar errores
$conn->query("ALTER TABLE profesionales ADD COLUMN IF NOT EXISTS creditos INT DEFAULT 0");

// === Guardar profesional (solo la primera categoría como principal) ===
$categoria_principal = (int)reset($categorias);

try {
  $stmt = $conn->prepare("INSERT INTO profesionales
  (id_global, nombre, apellido, email, whatsapp, acepta_terminos,
   direccion, barrio, ciudad, provincia, categoria_id, experiencia,
   foto_profesional, foto_matricula, descripcion, rating, password,
   email_verificado, created_at, verificado, fecha_registro,
   estado, estado_servicio, creditos)
   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'fuera', 0, ?)");

  $stmt->bind_param(
    "ssssisssssiisssdsiissi",
    $id_global, $nombre, $apellido, $email, $whatsapp, $acepta_terminos,
    $direccion, $barrio, $ciudad, $provincia, $categoria_principal, $experiencia,
    $foto_profesional, $foto_matricula, $descripcion, $rating, $password_hash,
    $email_verificado, $fecha, $verificado, $fecha, $creditos_bienvenida
  );

  $stmt->execute();
  $prof_id = $conn->insert_id;
  $stmt->close();

  // === Guardar DNI en tabla asociada (Solución agentes) ===
  if ($foto_dni_frente || $foto_dni_dorso) {
    try {
      $stmtDoc = $conn->prepare("INSERT INTO profesionales_documentos (profesional_id, dni_frente, dni_dorso) VALUES (?, ?, ?)");
      $stmtDoc->bind_param("iss", $prof_id, $foto_dni_frente, $foto_dni_dorso);
      $stmtDoc->execute();
      $stmtDoc->close();
    } catch (Throwable $e) {
      error_log('[REG_PROFESIONAL_DOCS] ' . $e->getMessage());
    }
  }

  // === Guardar Fecha de Nacimiento en tabla aparte ===
  if ($fecha_nacimiento) {
    try {
      // Intentar crear la tabla si no existe (por las dudas)
      $conn->query("CREATE TABLE IF NOT EXISTS profesionales_datos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        profesional_id INT NOT NULL,
        fecha_nacimiento DATE,
        FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
      )");
      $stmtBirth = $conn->prepare("INSERT INTO profesionales_datos (profesional_id, fecha_nacimiento) VALUES (?, ?)");
      $stmtBirth->bind_param("is", $prof_id, $fecha_nacimiento);
      $stmtBirth->execute();
      $stmtBirth->close();
    } catch (Throwable $e) {
      error_log('[REG_PROFESIONAL_BIRTH] ' . $e->getMessage());
    }
  }

} catch (Throwable $e) {
  error_log('[REG_PROFESIONAL_INSERT] ' . $e->getMessage());
  echo json_encode(["ok" => false, "msg" => "Error al guardar en la base de datos."]);
  exit;
}

// === Insertar categorías en tabla intermedia ===
try {
  if (!empty($categorias) && is_array($categorias)) {
    $stmtCat = $conn->prepare("INSERT IGNORE INTO profesional_categorias (profesional_id, categoria_id) VALUES (?, ?)");
    foreach ($categorias as $cat_id) {
      $cat_id = (int)$cat_id;
      if ($cat_id > 0) {
        $stmtCat->bind_param('ii', $prof_id, $cat_id);
        $stmtCat->execute();
      }
    }
    $stmtCat->close();
  }
} catch (Throwable $e) {
  error_log('[REG_PROFESIONAL_CATS] ' . $e->getMessage());
}

// === Enviar correo de verificación ===
$url = "https://buscotec.click/backend/verificar_profesional.php?email=" . urlencode($email) . "&token=" . urlencode($id_global);

$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $config = require __DIR__ . '/config/mailer.env.php';
  $mail->Host = $config['SMTP_HOST'];
  $mail->SMTPAuth = true;
  $mail->Username = $config['SMTP_USER'];
  $mail->Password = $config['SMTP_PASS'];
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port = 465;

  $mail->setFrom('oscarns@gmail.com', 'BuscoTec');
  $mail->addAddress($email, "$nombre $apellido");
  $mail->isHTML(true);
  $mail->Subject = 'Verificá tu cuenta - BuscoTec';
  $mail->Body = "
    <h2>¡Hola, $nombre!</h2>
    <p>Gracias por registrarte en <b>BuscoTec</b>.</p>
    <p>Para activar tu cuenta profesional, hacé clic en el siguiente botón:</p>
    <p><a href='$url' style='background:#1877f2;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>Verificar cuenta</a></p>
    <p>O copiá y pegá este enlace en tu navegador:<br><small>$url</small></p>
    <hr><p style='color:#6c757d'>BuscoTec © 2025</p>";

  $mail->send();
} catch (Throwable $e) {
  error_log('[MAIL_REG_PRO] ' . $e->getMessage());
}

// === Respuesta final ===
echo json_encode(["ok" => true, "msg" => "Registro exitoso. Revisá tu correo para verificar la cuenta."]);
exit;
?>
