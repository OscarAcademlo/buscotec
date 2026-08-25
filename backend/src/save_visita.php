<?php
// backend/save_visita.php — Registra visitas filtrando bots
require_once __DIR__ . '/conexion.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
}
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$page = $_GET['page'] ?? 'home';

// Filtro rápido de bots antes de insertar para no ensuciar la DB
$is_bot = false;
$bot_keywords = [
    'bot', 'spider', 'crawler', 'google', 'bing', 'yandex', 'slurp', 'baiduspider', 
    'facebookexternalhit', 'ia_archiver', 'mediapartners-google', 'adsbot-google', 
    'bingpreview', 'whatsapp', 'telegram', 'twitterbot', 'semrush', 'ahrefs', 
    'mj12bot', 'rogerbot', 'exabot', 'dotbot', 'petalbot', 'yacybot', 'libwww-perl',
    'python', 'curl', 'wget', 'headless', 'seo', 'lighthouse', 'gtmetrix'
];
foreach ($bot_keywords as $keyword) {
    if (stripos($ua, $keyword) !== false) {
        $is_bot = true;
        break;
    }
}

$bot_flag = $is_bot ? 1 : 0;

if (!empty($ip)) {
    // Evitar duplicados rápidos (misma IP en la misma página en los últimos 5 minutos)
    $stmt = $conn->prepare("SELECT id FROM visitas WHERE ip = ? AND pagina = ? AND fecha > (NOW() - INTERVAL 5 MINUTE) LIMIT 1");
    $stmt->bind_param("ss", $ip, $page);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO visitas (ip, user_agent, pagina, is_bot) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $ip, $ua, $page, $bot_flag);
        $stmt->execute();
    }
}

// Retornar pixel transparente o respuesta vacía
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>
