<?php
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !$conn) {
    echo json_encode(["ok" => false, "error" => "No db connection"]);
    exit;
}

// Crear tabla de visitas si no existe
$conn->query("CREATE TABLE IF NOT EXISTS visitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    pagina VARCHAR(255),
    is_bot TINYINT(1) DEFAULT 0
)");
// Asegurar que la columna existe si la tabla ya fue creada
$conn->query("ALTER TABLE visitas ADD COLUMN IF NOT EXISTS is_bot TINYINT(1) DEFAULT 0");

// Corregir retroactivamente registros antiguos o marcados incorrectamente como humanos
$conn->query("UPDATE visitas SET is_bot = 1 WHERE is_bot = 0 AND (
    LOWER(user_agent) LIKE '%bot%' OR 
    LOWER(user_agent) LIKE '%spider%' OR 
    LOWER(user_agent) LIKE '%crawler%' OR 
    LOWER(user_agent) LIKE '%google%' OR 
    LOWER(user_agent) LIKE '%bing%' OR 
    LOWER(user_agent) LIKE '%yandex%' OR 
    LOWER(user_agent) LIKE '%slurp%' OR 
    LOWER(user_agent) LIKE '%baiduspider%' OR 
    LOWER(user_agent) LIKE '%facebookexternalhit%' OR
    LOWER(user_agent) LIKE '%whatsapp%' OR
    LOWER(user_agent) LIKE '%telegram%' OR
    LOWER(user_agent) LIKE '%twitterbot%' OR
    LOWER(user_agent) LIKE '%seo%'
)");

$res = ["ok" => false];

try {
    // Definimos patrones comunes de bots para filtrar en correos (Reales = Verificados + Sin patrón bot)
    $bot_email_pattern = "email NOT LIKE '%bot%' AND email NOT LIKE '%test%' AND email NOT LIKE '%example%'";

    // --- PROFESIONALES ---
    // Total
    $c_profe_total = $conn->query("SELECT COUNT(*) AS c FROM profesionales")->fetch_assoc()['c'] ?? 0;
    // Real (Verificado + No patrón bot)
    $c_profe_real = $conn->query("SELECT COUNT(*) AS c FROM profesionales WHERE verificado = 1 AND $bot_email_pattern")->fetch_assoc()['c'] ?? 0;
    // Sin verificar
    $c_profe_unverif = $conn->query("SELECT COUNT(*) AS c FROM profesionales WHERE verificado = 0")->fetch_assoc()['c'] ?? 0;
    // Bots detectados por email
    $c_profe_bots = $conn->query("SELECT COUNT(*) AS c FROM profesionales WHERE email LIKE '%bot%' OR email LIKE '%test%' OR email LIKE '%example%'")->fetch_assoc()['c'] ?? 0;

    // --- USUARIOS ---
    // Total
    $c_user_total = $conn->query("SELECT COUNT(*) AS c FROM usuarios")->fetch_assoc()['c'] ?? 0;
    // Real (Verificado + No patrón bot)
    $c_user_real = $conn->query("SELECT COUNT(*) AS c FROM usuarios WHERE verificado = 1 AND $bot_email_pattern")->fetch_assoc()['c'] ?? 0;
    // Sin verificar
    $c_user_unverif = $conn->query("SELECT COUNT(*) AS c FROM usuarios WHERE verificado = 0")->fetch_assoc()['c'] ?? 0;
    // Bots detectados por email
    $c_user_bots = $conn->query("SELECT COUNT(*) AS c FROM usuarios WHERE email LIKE '%bot%' OR email LIKE '%test%' OR email LIKE '%example%'")->fetch_assoc()['c'] ?? 0;

    // --- VISITAS ---
    // Total
    $c_visitas_total = $conn->query("SELECT COUNT(*) AS c FROM visitas")->fetch_assoc()['c'] ?? 0;
    // Reales (is_bot = 0)
    $c_visitas_real = $conn->query("SELECT COUNT(*) AS c FROM visitas WHERE is_bot = 0")->fetch_assoc()['c'] ?? 0;
    // Bots (is_bot = 1)
    $c_visitas_bots = $conn->query("SELECT COUNT(*) AS c FROM visitas WHERE is_bot = 1")->fetch_assoc()['c'] ?? 0;

    // --- ÚLTIMAS VISITAS ---
    $q_visitas = $conn->query("SELECT ip, fecha, user_agent, pagina, is_bot FROM visitas ORDER BY id DESC LIMIT 50");
    
    $list_visitas = [];
    if($q_visitas) {
        $ip_cache = [];
        while($r = $q_visitas->fetch_assoc()) {
            $ip = $r['ip'];
            $location = '';
            
            // Ya tenemos is_bot en la tabla, pero mantenemos la lógica de marcado visual por si acaso
            $r['es_bot'] = (int)$r['is_bot'] === 1;

            if ($ip === '127.0.0.1' || $ip === '::1') {
                $location = 'Localhost';
            } else {
                if (!isset($ip_cache[$ip])) {
                    $ctx = stream_context_create(['http' => ['timeout' => 1]]);
                    $geo_data = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,regionName,city,status", false, $ctx);
                    if ($geo_data) {
                        $geo = json_decode($geo_data, true);
                        if (is_array($geo) && isset($geo['status']) && $geo['status'] === 'success') {
                            $location = trim($geo['city'] . ', ' . $geo['regionName'] . ' (' . $geo['country'] . ')');
                        }
                    }
                    if (empty($location)) { $location = 'Desconocida'; }
                    $ip_cache[$ip] = $location;
                } else {
                    $location = $ip_cache[$ip];
                }
            }
            $r['localidad'] = $location;
            $list_visitas[] = $r;
        }
    }
    
    $res = [
        "ok" => true,
        "profesionales" => [
            "total" => (int)$c_profe_total,
            "reales" => (int)$c_profe_real,
            "sin_verificar" => (int)$c_profe_unverif,
            "bots" => (int)$c_profe_bots
        ],
        "usuarios" => [
            "total" => (int)$c_user_total,
            "reales" => (int)$c_user_real,
            "sin_verificar" => (int)$c_user_unverif,
            "bots" => (int)$c_user_bots
        ],
        "visitas" => [
            "total" => (int)$c_visitas_total,
            "reales" => (int)$c_visitas_real,
            "bots" => (int)$c_visitas_bots
        ],
        "ultimas_visitas" => $list_visitas
    ];
} catch (Throwable $e) {
    $res["error"] = $e->getMessage();
}

echo json_encode($res);
