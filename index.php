<?php
// V10.2 ANTIGRAVITY FIX - 2026-04-03
// BuscoTec - index.php - Actualizado: 16:30
// Integración Webpushr VAPID FINAL corregida.
error_reporting(E_ALL);
// --- Redirección agresiva a WWW (Crítico para Webpushr) ---
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'www.') === false && strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
    header("Location: https://www.buscotec.com.ar" . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

require_once __DIR__ . '/backend/session_boot.php';
require_once __DIR__ . '/backend/conexion.php';
if (isset($conn) && $conn) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pagina = $_SERVER['REQUEST_URI'] ?? '/';

        // Evitar duplicados rápidos (misma IP y página en los últimos 5 minutos)
        $chk = $conn->prepare("SELECT id FROM visitas WHERE ip = ? AND pagina = ? AND fecha > (NOW() - INTERVAL 5 MINUTE) LIMIT 1");
        if ($chk) {
            $chk->bind_param("ss", $ip, $pagina);
            $chk->execute();
            $res_chk = $chk->get_result();
            if ($res_chk->num_rows === 0) {
                // Filtro rápido de bots
                $is_bot = 0;
                $bot_keywords = ['bot', 'spider', 'crawler', 'google', 'bing', 'whatsapp', 'telegram', 'seo'];
                foreach ($bot_keywords as $keyword) {
                    if (stripos($ua, $keyword) !== false) {
                        $is_bot = 1;
                        break;
                    }
                }

                $stmt = $conn->prepare("INSERT INTO visitas (ip, user_agent, pagina, is_bot) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssi", $ip, $ua, $pagina, $is_bot);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $chk->close();
        }
    } catch (Throwable $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-18059169740">
    </script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());

        gtag('config', 'AW-18059169740');
    </script>
    <meta charset="UTF-8">
    <script>
        // Redirección ultra-agresiva a WWW + Limpieza de Service Worker (Crítico para PWA/Caché)
        if (window.location.hostname === 'buscotec.com.ar') {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function (registrations) {
                    for (let registration of registrations) { registration.unregister(); }
                    window.location.replace('https://www.buscotec.com.ar' + window.location.pathname + window.location.search);
                }).catch(function () {
                    window.location.replace('https://www.buscotec.com.ar' + window.location.pathname + window.location.search);
                });
            } else {
                window.location.replace('https://www.buscotec.com.ar' + window.location.pathname + window.location.search);
            }
        }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuscoTec — Encontrá profesionales en Bariloche, Neuquén, Alto Valle de Río Negro y Neuquén</title>
    <meta name="description" content="Conectamos personas que necesiten un servicio, con quines saben hacerlo.">

    <link rel="icon" href="/img/icons/icon-192.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1877f2">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Bootstrap para Modales solamente -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="apple-touch-icon" href="img/icons/icon-192.png">
    <style>
        /* RESTRICCIÓN GLOBAL DE TAMAÑO DE IMÁGENES (EVITA IMÁGENES GIGANTES) */
        img {
            max-width: 100%;
            height: auto;
        }

        .prof-card-img, .prof-card-mini img, .app-prof-card-img {
            width: 60px !important;
            height: 60px !important;
            min-width: 60px !important;
            max-width: 60px !important;
            min-height: 60px !important;
            max-height: 60px !important;
            border-radius: 16px !important;
            object-fit: cover !important;
            flex-shrink: 0 !important;
        }

        /* ============================================================ */
        /* ESTILOS DE LA APP MÓVIL (INSPIRADOS EN CAPTURA DE PANTALLA) */
        /* ============================================================ */
        .app-header-hero {
            background: linear-gradient(180deg, #0b3558 0%, #0d6efd 100%);
            border-radius: 0 0 36px 36px;
            padding: 100px 24px 36px;
            color: #ffffff;
            position: relative;
            box-shadow: 0 10px 30px rgba(13, 110, 253, 0.22);
            margin-bottom: 24px;
        }

        .app-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .app-header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #ffffff;
        }

        .app-header-brand img {
            height: 42px;
            width: auto;
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .app-header-brand-title {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .app-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .app-icon-circle-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #ffffff !important;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .app-icon-circle-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: scale(1.05);
        }

        .app-greeting-sub {
            font-size: 1.15rem;
            opacity: 0.92;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .app-greeting-title {
            font-size: 2.2rem;
            font-weight: 900;
            letter-spacing: -1px;
            margin-bottom: 24px;
            line-height: 1.15;
        }

        .app-search-pill-box {
            background: #ffffff;
            border-radius: 100px;
            padding: 10px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
        }

        .app-search-pill-box i {
            color: #0d6efd;
            font-size: 1.35rem;
        }

        .app-search-pill-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 1.05rem;
            color: #1e293b;
            font-weight: 500;
            background: transparent;
        }

        /* Categorías Pastel */
        .app-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding: 0 4px;
        }

        .app-section-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0;
            letter-spacing: -0.5px;
        }

        .app-categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 36px;
        }

        @media (max-width: 768px) {
            .app-categories-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            .app-categories-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }
        }

        .app-cat-card-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #334155;
            transition: all 0.2s ease;
        }

        .app-cat-card-item:hover {
            transform: translateY(-4px);
            color: #0d6efd;
        }

        .app-cat-icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        @media (max-width: 576px) {
            .app-cat-icon-wrapper {
                width: 62px;
                height: 62px;
                font-size: 1.75rem;
                border-radius: 18px;
            }
        }

        .app-cat-card-item:hover .app-cat-icon-wrapper {
            transform: scale(1.08);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .app-cat-label {
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.25;
        }

        /* Mapa y tarjetas de profesionales cerca */
        .app-nearby-map-card {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.06);
            height: 230px;
            margin-bottom: 24px;
            position: relative;
        }

        .app-prof-slider {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding: 4px 4px 16px;
            scrollbar-width: thin;
        }

        .app-prof-card-item {
            background: #ffffff;
            border-radius: 20px;
            padding: 16px 20px;
            min-width: 280px;
            max-width: 330px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.06);
            text-decoration: none;
            color: #0f172a;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .app-prof-card-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .app-prof-card-img {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .app-prof-card-info {
            flex-grow: 1;
        }

        .app-prof-card-title {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 2px;
            color: #0f172a;
        }

        .app-prof-card-sub {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .app-prof-card-arrow {
            color: #0d6efd;
            font-size: 1.4rem;
        }

        /* BARRA DE NAVEGACIÓN INFERIOR MÓVIL (BOTTOM NAV) */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 68px;
            background: #ffffff;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            z-index: 1040;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
            justify-content: space-around;
            align-items: center;
            padding: 0 8px;
        }

        @media (max-width: 768px) {
            .mobile-bottom-nav {
                display: flex;
            }
            body {
                padding-bottom: 75px !important;
            }
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 3px;
            width: 25%;
            transition: color 0.2s ease;
        }

        .mobile-nav-item i {
            font-size: 1.35rem;
        }

        .mobile-nav-item.active,
        .mobile-nav-item:hover {
            color: #0d6efd;
        }

        /* ESTILOS EXACTOS DE LA LANDING ORIGINAL */

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --blue-dark: #0b3558;
            --blue-main: #1565C0;
            --blue-light: #1877f2;
            --green: #2e7d32;
            --green-light: #43a047;
            --text: #1a1a2e;
            --text-muted: #666;
            --bg: #f8f9fc;
            --white: #ffffff;
            --radius: 16px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);

        }

        /* --- NAVBAR --- */
        .landing-nav {
            background-color: gray;
            backdrop-filter: blur(12px);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .logo {
            text-decoration: none;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .logo img {
            height: 58px;
            width: auto;
            object-fit: contain;
            mix-blend-mode: multiply;
            transition: transform 0.25s ease;
        }

        .logo:hover img {
            transform: translateY(-1px) scale(1.03);
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav-links a {
            color: #1a3a5c;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #1877f2;
        }

        .btn-nav-login {
            background: #0b3558;
            color: #fff !important;
            padding: 10px 24px;
            border-radius: 100px;
            font-weight: 700;
            transition: background 0.2s;
        }

        .btn-nav-login:hover {
            background: #1877f2;
        }

        .menu-toggle {
            display: none;
            color: #1a3a5c;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* --- HERO --- */

        .hero {
            position: relative;
            /* Elimine width:100vw y margin por bugs de bordes blancos */
            width: 100%;
            min-height: calc(100vh - 80px);
            /* Ocupar pantalla */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            padding: 120px 24px 80px;
            overflow: hidden;
            /* Removido fallback background-color aquí para evitar solapamientos raros si la caché falla */
        }

        /* Capa de fondo con imagen interactiva (movimiento) */
        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(11, 53, 88, 0.82), rgba(24, 119, 242, 0.85)), url('img/plomeria.png?v=3') center center / cover no-repeat;
            background-color: #0b3558;
            /* Fallback aquí adentro */
            z-index: 0;
            /* Animación sutil y muy elegante (Zoom-in) */
            animation: heroBgMove 10s infinite alternate ease-in-out;
            transform: scale(1.05);
            /* Evita bordes blancos al hacer zoom */
        }

        @keyframes heroBgMove {
            0% {
                transform: scale(1.0);
            }

            100% {
                transform: scale(1.25);
            }
        }

        .hero>* {
            position: relative;
            z-index: 10;
        }

        /* Adaptar márgenes móviles */
        @media (max-width: 768px) {
            .hero {
                padding: 100px 20px 40px;
            }

            .hero-stats {
                flex-direction: column;
                gap: 20px;
                margin-top: 40px;
            }

            .stat-divider {
                display: none;
            }

            .cta-btns {
                flex-direction: column;
                width: 100%;
                max-width: 400px;
                margin: 40px auto 0;
            }

            .btn-cta-green,
            .btn-cta-orange {
                width: 100%;
                display: block;
            }
        }




        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
        }

        .hero h1 {
            color: #fff;
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -2px;
        }

        .hero h1 em {
            font-style: normal;
            color: rgba(255, 255, 255, 0.7);
        }

        .hero p {
            color: rgba(255, 255, 255, 0.9);
            font-size: clamp(1.1rem, 3vw, 1.35rem);
            line-height: 1.6;
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-btns {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary-hero {
            background: linear-gradient(135deg, #2e7d32, #43a047);
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 18px 40px;
            border-radius: 100px;
            text-decoration: none;
            box-shadow: 0 10px 40px rgba(46, 125, 50, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary-hero:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 50px rgba(46, 125, 50, 0.5);
        }

        .btn-secondary-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #E65100, #F57C00);
            /* Color naranja */
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 16px 32px;
            border-radius: 100px;
            border: none;
            text-decoration: none;
            box-shadow: 0 8px 30px rgba(230, 81, 0, 0.35);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-secondary-hero:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(230, 81, 0, 0.45);
        }

        .hero-stats {
            margin-top: 60px;
            display: flex;
            gap: 40px;
            justify-content: center;
            align-items: center;
        }

        .stat {
            text-align: left;
        }

        .stat-num {
            color: #fff;
            font-size: 2rem;
            font-weight: 800;
            display: block;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-divider {
            width: 1px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
        }

        /* --- CATEGORÍAS --- */
        section {
            padding: 100px 24px;
            max-width: 100%;
            margin: 0 auto;
        }

        .section-label {
            color: white;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            text-align: center;
        }

        .section-title {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            text-align: center;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .section-sub {
            text-align: center;
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 60px;
            line-height: 1.6;
        }

        .cats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .cat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .cat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            border-color: var(--blue-light);
        }

        .cat-emoji {
            font-size: 2rem;
            background: var(--bg);
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .cat-card:hover .cat-emoji {
            background: var(--blue-light);
            color: #fff;
            transform: scale(1.1);
        }

        .cat-name {
            font-weight: 700;
            font-size: 1.15rem;
        }

        /* --- COMO FUNCIONA --- */
        #como-funciona {
            background: var(--blue-dark);
            color: #fff;
            padding: 100px 24px;
        }

        .cf-header {
            text-align: center;
            margin-bottom: 80px;
        }

        .cf-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .cf-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 48px;
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cf-card h3 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 32px;
        }

        .step {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
        }

        .step-num {
            width: 32px;
            height: 32px;
            background: var(--blue-light);
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 800;
            flex-shrink: 0;
            font-size: 0.9rem;
        }

        .step-text h4 {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .step-text p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .cf-btn {
            display: inline-block;
            width: 100%;
            padding: 16px;
            border-radius: 100px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            margin-top: 20px;
            transition: all 0.2s;
        }

        .btn-search {
            background: var(--green);
            color: #fff;
        }

        .btn-reg {
            background: #fff;
            color: var(--blue-dark);
        }

        /* --- CTA FINAL --- */
        .cta-final {
            background: linear-gradient(135deg, #0b3558, #1877f2);
            text-align: center;
            padding: 120px 24px;
            color: #fff;
            max-width: 100%;
            margin: 0;
        }

        .cta-final h2 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            margin-bottom: 40px;
        }

        .cta-btns {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cta-green {
            background: linear-gradient(135deg, #2e7d32, #43a047);
            color: #fff;
            font-weight: 700;
            padding: 16px 36px;
            border-radius: 100px;
            text-decoration: none;
            font-size: 1rem;
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
            transition: transform 0.2s;
        }

        .btn-cta-orange {
            background: linear-gradient(135deg, #E65100, #F57C00);
            /* Color naranja */
            color: #fff;
            font-weight: 700;
            padding: 16px 36px;
            border-radius: 100px;
            text-decoration: none;
            font-size: 1rem;
            box-shadow: 0 6px 20px rgba(230, 81, 0, 0.3);
            transition: transform 0.2s;
        }

        .btn-cta-green:hover,
        .btn-cta-orange:hover {
            transform: translateY(-2px);
        }

        /* --- FOOTER --- */
        footer.bg-dark {
            max-width: 100% !important;
            margin: 0;
            display: block !important;
        }

        footer {
            padding: 60px 24px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            max-width: 100%;
            margin: 0;
            display: block;
        }

        .footer-logo {
            font-weight: 800;
            font-size: 1.25rem;
            text-decoration: none;
            color: var(--text);
        }

        .footer-links {
            display: flex;
            gap: 32px;
        }

        .footer-links a {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* --- RESPONSIVO --- */
        @media (max-width: 992px) {
            .cf-cols {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: flex !important;
                margin-left: auto;
                margin-right: 15px;
                gap: 15px;
            }

            .nav-links>*:not(#notifBell) {
                display: none !important;
            }

            .menu-toggle {
                display: block;
            }

            .hero {
                padding-top: 140px;
            }

            .hero-stats {
                flex-direction: column;
                gap: 24px;
                margin-top: 40px;
            }

            .stat {
                text-align: center;
            }

            .stat-divider {
                width: 40px;
                height: 1px;
            }

            footer {
                flex-direction: column;
                gap: 32px;
                text-align: center;
            }

            .footer-links {
                flex-direction: column;
                gap: 16px;
            }
        }

        /* --- ANIMACIONES --- */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeUp 0.8s forwards cubic-bezier(0.4, 0, 0.2, 1);
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* Ajustes extra para coexistir con Bootstrap sin romperse */
        body {
            background: var(--bg) !important;
            padding-top: 0 !important;
        }

        .landing-nav {
            background: linear-gradient(135deg, #0b3558, #1877f2) !important;
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1050;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .landing-nav .nav-links a {
            color: #ffffff !important;
            font-weight: 600;
        }

        .landing-nav .logo img {
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 10px;
            height: 36px;
        }

        .hero {
            margin-top: 0;
            min-height: 100vh;
        }

        /* Reset de un estilo intrusivo de bootstrap en los containers si lo hubiera */
        .cats-grid a {
            text-decoration: none;
        }
        .store-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .btn-store {
            background: #0b3558;
            color: #fff !important;
            text-decoration: none;
            padding: 10px 24px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.15);
            min-width: 200px;
            text-align: left;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-store:hover {
            background: #1877f2;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(24, 119, 242, 0.4);
        }

        .btn-store i {
            font-size: 28px;
        }

        .btn-store .btn-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .btn-store .btn-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            opacity: 0.85;
            font-weight: 500;
        }

        .btn-store .btn-main {
            font-size: 1.15rem;
            font-weight: 800;
        }

        @media (max-width: 768px) {
            .store-buttons {
                flex-direction: column;
                align-items: center;
                gap: 12px;
            }
            .btn-store {
                width: 100%;
                max-width: 280px;
            }
        }
    </style>

    <!-- Webpushr -->
    <script>
        // FORZAR RESET DE WEBPUSHR Y SUSCRIPCIONES VIEJAS (SOLO UNA VEZ POR VERSIÓN)
        // Esto soluciona el loop de "Subscriber not found" al cambiar claves VAPID o Dominio
        if (!localStorage.getItem('wp_reset_v8')) {
            localStorage.setItem('wp_reset_v8', '1');
            localStorage.removeItem('webpushr_sid');
            try { window.indexedDB.deleteDatabase("webpushr"); } catch (e) { }
            try { window.indexedDB.deleteDatabase("webpushr_pn"); } catch (e) { }
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.pushManager.getSubscription().then(sub => {
                        if (sub) {
                            sub.unsubscribe().then(() => {
                                console.log('Vieja suscripcion eliminada');
                            });
                        }
                    });
                });
            }
        }

        (function (w, d, s, id) {
            if (typeof (w.webpushr) !== 'undefined') return;
            w.webpushr = w.webpushr || function () { (w.webpushr.q = w.webpushr.q || []).push(arguments) };
            var js, fjs = d.getElementsByTagName(s)[0];
            js = d.createElement(s); js.id = id; js.async = 1;
            js.src = "https://cdn.webpushr.com/app.min.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(window, document, 'script', 'webpushr-jssdk'));

        webpushr('setup', {
            'key': 'BA4ex51b1wyKKfklcCIUAc-RE8qed1pTZyZIp8INY2fx46pRCxyjZfF4VW8Tt8d2vvNRnefZu7YAF0b4tuD20LI',
            'serviceWorker': '/sw.js',
            'integration': 'pwa'
        });
    </script>
</head>

<body>


    <!-- MOBILE NAV -->

    <div id="mobile-menu"
        style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--blue-dark); z-index: 2000; display: none; flex-direction: column; align-items: center; justify-content: center; gap: 32px;">
        <div onclick="closeMenu()"
            style="position: absolute; top: 24px; right: 24px; color: #fff; font-size: 2rem; cursor: pointer;">✕</div>
        <a href="/categoria.php" onclick="closeMenu()"
            style="color: #fff; font-size: 1.5rem; text-decoration: none; font-weight: 700;">Categorías</a>
        <a href="#como-funciona" onclick="closeMenu()"
            style="color: #fff; font-size: 1.5rem; text-decoration: none; font-weight: 700;">¿Cómo funciona?</a>
        <a href="/contactanos.html"
            style="color: #fff; font-size: 1.5rem; text-decoration: none; font-weight: 700;">Contacto</a>

        <a href="/perfil_ver.html" class="auth d-none"
            style="color: #fff; font-size: 1.5rem; text-decoration: none; font-weight: 700;">Mi Perfil</a>
        <a href="/pagos.html" class="auth d-none prof-only"
            style="color: #fff; font-size: 1.5rem; text-decoration: none; font-weight: 700;">Estado de cuenta</a>
        <a href="/admin.html" class="auth d-none admin-link-only"
            style="color: #FFA000; font-size: 1.5rem; text-decoration: none; font-weight: 800;">Admin</a>
        <a href="#" id="logoutBtn" class="btn-nav-login auth d-none"
            style="font-size: 1.5rem; background:transparent; border:1px solid #fff; color:#fff !important;">Salir</a>

        <a href="/seleccion_registro.html" class="no-auth"
            style="color: #fff; font-size: 1.5rem; text-decoration: none; font-weight: 700;">Registrarme</a>
        <a href="#" class="btn-nav-login no-auth" data-bs-toggle="modal" data-bs-target="#loginModal"
            onclick="closeMenu()" style="font-size: 1.5rem;">Iniciar sesión</a>
    </div>

    <nav class="landing-nav">
        <a href="/" class="logo" style="display: inline-flex; align-items: center; text-decoration: none;">
            <div style="background: #ffffff; padding: 6px 16px; border-radius: 28px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18); display: flex; align-items: center; border: 1px solid rgba(255, 255, 255, 0.4); transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                <img src="img/logo_web.png" alt="BuscoTec" style="height: 38px; width: auto; max-height: 38px; object-fit: contain; filter: contrast(1.1) brightness(1.02);">
            </div>
        </a>
        <div class="nav-links">
            <a href="#como-funciona" onclick="closeMenu()">¿Cómo funciona?</a>
            <a href="/categoria.php">Categorías</a>
            <a href="/contactanos.html">Contacto</a>

            <!-- 🔔 Campanita y Notificaciones -->
            <a href="#" id="notifBell" class="d-none"
                style="text-decoration:none; position:relative; font-size: 1.2rem; cursor:pointer;"
                data-bs-toggle="modal" data-bs-target="#modalMensajes">
                🔔
                <span id="notifCount" class="d-none"
                    style="position:absolute; top:-5px; right:-10px; font-size:0.65rem; background:#dc3545; color:white; border-radius:50%; padding:2px 5px; font-family:sans-serif; font-weight:bold;">0</span>
            </a>

            <!-- 👋 Bienvenida -->
            <span id="userGreeting" class="d-none"
                style="color:#fff; font-weight:700; margin-left:8px; margin-right:8px;">👋
                <span id="userName"></span>
                <button id="btnToggleUbicacion" class="btn btn-sm text-white ms-2 px-2 py-0 align-middle rounded-pill fw-bold border border-white-50" style="background: rgba(255,255,255,0.25); font-size: 0.75rem;" title="Mostrar u ocultar mi ubicación actual" onclick="toggleUbicacionUsuario()">
                    <i class="bi bi-geo-alt-fill text-warning" id="icoUbicacion"></i> <span id="lblUbicacion">📍 Ubicación: Visible</span>
                </button>
            </span>

            <!-- 👤 Logueados -->
            <a href="/perfil_ver.html" class="auth d-none">Mi Perfil</a>
            <a href="/pagos.html" class="auth d-none prof-only">Estado de cuenta</a>
            <a href="/admin.html" class="auth d-none admin-link-only" style="color:#FFA000; font-weight:800;">Admin</a>
            <a href="#" id="logoutBtnTop" class="btn-nav-login auth d-none"
                style="background:transparent; border:1px solid #fff; color:#fff !important;">Salir</a>

            <!-- 🚪 No Logueados -->
            <a href="/seleccion_registro.html" id="registerBtnTop" class="no-auth">Registrarme</a>
            <a href="#" class="btn-nav-login no-auth" id="loginBtnTop" data-bs-toggle="modal"
                data-bs-target="#loginModal">Iniciar sesión</a>
        </div>
        <div class="menu-toggle" onclick="openMenu()">☰</div>
    </nav>

    <!-- CABECERA HERO ESTILO APP MÓVIL (INSPIRADO EN CAPTURA) -->
    <div class="app-header-hero">
        <div class="app-greeting-sub d-flex align-items-center flex-wrap gap-2">
            <span>Hola, <span id="appGreetingName">Visitante</span></span>
            <button id="btnToggleUbicacionHero" class="btn btn-sm text-white px-2 py-1 rounded-pill fw-bold border border-white-50 auth d-none" style="background: rgba(255,255,255,0.2); font-size: 0.75rem;" title="Mostrar u ocultar mi ubicación actual" onclick="toggleUbicacionUsuario()">
                <i class="bi bi-geo-alt-fill text-warning" id="icoUbicacionHero"></i> <span id="lblUbicacionHero">📍 Ubicación: Visible</span>
            </button>
        </div>
        <div class="app-greeting-title">¿qué necesitás hoy?</div>

        <div class="app-search-pill-box">
            <i class="bi bi-search"></i>
            <input type="text" id="appHeroSearchInput" class="app-search-pill-input" placeholder="Buscar profesionales, servicios u oficios..." autocomplete="off">
        </div>
        <ul id="appHeroSugerencias" class="list-group position-absolute w-100 shadow-lg d-none" style="z-index: 1050; max-height: 250px; overflow-y: auto; margin-top: 6px; border-radius: 16px;"></ul>
    </div>

    <!-- SECCIÓN PRINCIPAL: CATEGORÍAS Y PROFESIONALES CERCA -->
    <div class="container-fluid px-3 px-md-4">
        
        <!-- GRILLA DE CATEGORÍAS ESTILO APP -->
        <section id="categorias" class="pt-2 pb-4">
            <div class="app-section-header">
                <h3 class="app-section-title">Categorías de servicios</h3>
                <a href="/categoria.php" class="text-primary fw-bold text-decoration-none small">Ver todas <i class="bi bi-chevron-right"></i></a>
            </div>
            
            <div class="app-categories-grid" id="cats-grid">
                <!-- Se puebla dinámicamente con estética Pastel desde JS -->
            </div>
        </section>

        <!-- SECCIÓN PROFESIONALES CERCA CON MAPA Y TARJETAS -->
        <section id="profesionales-cerca" class="pb-5">
            <div class="app-section-header">
                <h3 class="app-section-title">Profesionales cerca</h3>
                <span class="badge bg-primary-subtle text-primary fw-bold rounded-pill px-3 py-2" id="badge-city"><i class="bi bi-geo-alt-fill"></i> Bariloche, Neuquén, Alto Valle de Río Negro y Neuquén</span>
            </div>

            <!-- Mapa interactivo -->
            <div class="app-nearby-map-card mb-3">
                <div id="mapContainerApp" style="width: 100%; height: 100%;"></div>
            </div>

            <!-- Tarjetas de Profesionales Cercanos -->
            <div class="app-prof-slider" id="profCarousel">
                <div class="text-muted small py-3">Cargando profesionales cercanos...</div>
            </div>
        </section>

    </div>

    <!-- SECCIÓN HERO ORIGINAL Y ESTADÍSTICAS (PRESENTES PARA SEO Y COMPATIBILIDAD) -->
    <section class="hero d-none d-md-flex">
        <h1 class="fade-up delay-1">
            Encontrá lo que<br><em>necesitás en <span class="dynamic-city">Bariloche, Neuquén, Alto Valle de Río Negro y Neuquén</span></em>
        </h1>
        <p class="fade-up delay-2">
            Conectamos personas que necesitan un servicio con profesionales y oficios en <span
                class="dynamic-city">Bariloche, Neuquén, Alto Valle de Río Negro y Neuquén</span>.
        </p>
        <div class="cta-btns fade-up delay-3 position-relative" style="z-index: 10; margin-top: 40px;">
            <a href="/categoria.php" class="btn-cta-green" style="font-size: 1.1rem; padding: 18px 40px;">Buscar un
                profesional</a>
            <a href="/seleccion_registro.html" class="btn-cta-orange"
                style="font-size: 1.1rem; padding: 18px 40px;">Ofrecer mi
                servicio</a>
        </div>

        <!-- Botones de Descarga App -->
        <div class="store-buttons fade-up delay-4">
            <a href="https://apps.apple.com/ar/app/buscotec/id6763900435" target="_blank" class="btn-store">
                <i class="bi bi-apple"></i>
                <div class="btn-text">
                    <span class="btn-label">Descargar en</span>
                    <span class="btn-main">App Store</span>
                </div>
            </a>
            <a href="https://play.google.com/store/apps/details?id=com.buscotec.mobile" target="_blank" class="btn-store">
                <i class="bi bi-google-play"></i>
                <div class="btn-text">
                    <span class="btn-label">Disponible en</span>
                    <span class="btn-main">Google Play</span>
                </div>
            </a>
        </div>
        <div class="hero-stats fade-up delay-4">
            <div class="stat">
                <div class="stat-num" id="stat-profes">+200</div>
                <div class="stat-label">Profesionales</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat">
                <div class="stat-num" id="stat-cats">+15</div>
                <div class="stat-label">Categorías</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat">
                <div class="stat-num">100%</div>
                <div class="stat-label">Local · <span class="dynamic-city">Bariloche, Neuquén, Alto Valle de Río Negro y Neuquén</span></div>
            </div>
        </div>
    </section>

    <!-- NAVEGACIÓN INFERIOR MÓVIL ESTILO APP -->
    <nav class="mobile-bottom-nav">
        <a href="/" class="mobile-nav-item active">
            <i class="bi bi-house-door-fill"></i>
            <span>Inicio</span>
        </a>
        <a href="#categorias" class="mobile-nav-item">
            <i class="bi bi-grid-fill"></i>
            <span>Categorías</span>
        </a>
        <a href="#" class="mobile-nav-item auth d-none" data-bs-toggle="modal" data-bs-target="#modalMensajes">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Mensajes</span>
        </a>
        <a href="/mensajes.html" class="mobile-nav-item no-auth">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Mensajes</span>
        </a>
        <a href="/perfil_ver.html" class="mobile-nav-item auth d-none">
            <i class="bi bi-person-fill"></i>
            <span>Perfil</span>
        </a>
        <a href="#" class="mobile-nav-item no-auth" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-person-fill"></i>
            <span>Perfil</span>
        </a>
    </nav>

    <!-- CÓMO FUNCIONA -->
    <div id="como-funciona">
        <div class="inner">
            <div class="cf-header">
                <div class="section-label">Simple, rápido y seguro</div>
                <h2 class="section-title">¿Cómo funciona?</h2>
                <p class="section-sub" style="margin: 0 auto;">
                    Pensado para dos perfiles: quienes necesitan un servicio y quienes lo ofrecen.
                </p>
            </div>
            <div class="cf-cols">
                <!-- Para Clientes -->
                <div class="cf-card fade-up delay-1">
                    <h3>Para Clientes</h3>
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text">
                            <h4>Buscás el profesional</h4>
                            <p>Elegís la categoría y ves los disponibles en tu zona.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text">
                            <h4>Consultás gratis</h4>
                            <p>Escribís tu consulta detallada y enviás fotos si es necesario.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text">
                            <h4>Te contactan</h4>
                            <p>Recibís presupuestos directamente en tu cuenta o por WhatsApp.</p>
                        </div>
                    </div>
                    <a href="/categoria.php" class="cf-btn btn-search">Buscar ahora</a>
                </div>

                <!-- Para Profesionales -->
                <div class="cf-card fade-up delay-2" style="background: var(--blue-main);">
                    <h3>Para Oficios</h3>
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text">
                            <h4>Te registrás</h4>
                            <p>Completás tu perfil con tus datos y especialidades.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text">
                            <h4>Recibís pedidos</h4>
                            <p>Te avisamos cada vez que alguien necesite un servicio en tu zona.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text">
                            <h4>Trabajás y ganás</h4>
                            <p>Gestionás tus clientes de forma simple y profesional.</p>
                        </div>
                    </div>
                    <a href="/seleccion_registro.html" class="cf-btn btn-reg">Registrarme gratis</a>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA FINAL -->
    <section class="cta-final">
        <h2 class="fade-up">¿Listo para empezar?</h2>
        <div class="cta-btns fade-up delay-1">
            <a href="/categoria.php" class="btn-cta-green">Buscar un profesional</a>
            <a href="/seleccion_registro.html" class="btn-cta-orange">Ofrecer mi servicio</a>
        </div>
        <!-- Botones de Descarga App Final -->
        <div class="store-buttons fade-up delay-2" style="margin-top: 50px;">
            <a href="https://apps.apple.com/ar/app/buscotec/id6763900435" target="_blank" class="btn-store" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3);">
                <i class="bi bi-apple"></i>
                <div class="btn-text">
                    <span class="btn-label">Descargar en</span>
                    <span class="btn-main">App Store</span>
                </div>
            </a>
            <a href="https://play.google.com/store/apps/details?id=com.buscotec.mobile" target="_blank" class="btn-store" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3);">
                <i class="bi bi-google-play"></i>
                <div class="btn-text">
                    <span class="btn-label">Disponible en</span>
                    <span class="btn-main">Google Play</span>
                </div>
            </a>
        </div>
    </section>



    <!-- MODAL ANUNCIO PROGRAMADO -->
    <div class="modal fade" id="modalAnuncioProgramado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 24px;">
                <div class="modal-header text-white border-0 py-3 px-4" style="background: linear-gradient(135deg, #0b3558, #1877f2);">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6" style="border-radius: 100px;">📢 Novedad</span>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="anuncioModalTituloMain">Anuncio</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Imagen Principal -->
                    <div id="anuncioMainImgContainer" class="text-center mb-3 d-none">
                        <img id="anuncioMainImg" src="" class="img-fluid rounded-4 shadow-sm" style="max-height: 320px; width: 100%; object-fit: cover;">
                    </div>
                    <!-- Texto Principal -->
                    <div class="mb-3">
                        <h4 id="anuncioTituloSub" class="fw-bold text-dark mb-2"></h4>
                        <p id="anuncioTextoMain" class="text-secondary fs-6" style="line-height: 1.6; white-space: pre-line;"></p>
                    </div>
                    <!-- Sub-tarjetas opcionales -->
                    <div id="anuncioSubGrid" class="row g-3 d-none mt-2">
                        <div id="anuncioSubCol1" class="col-md-6 d-none">
                            <div class="p-3 rounded-4 border bg-light h-100 shadow-sm">
                                <img id="anuncioSubImg1" src="" class="img-fluid rounded-3 mb-2 w-100 d-none" style="height: 140px; object-fit: cover;">
                                <h6 id="anuncioSubTitulo1" class="fw-bold text-primary mb-1"></h6>
                                <p id="anuncioSubTexto1" class="small text-muted mb-0" style="white-space: pre-line;"></p>
                            </div>
                        </div>
                        <div id="anuncioSubCol2" class="col-md-6 d-none">
                            <div class="p-3 rounded-4 border bg-light h-100 shadow-sm">
                                <img id="anuncioSubImg2" src="" class="img-fluid rounded-3 mb-2 w-100 d-none" style="height: 140px; object-fit: cover;">
                                <h6 id="anuncioSubTitulo2" class="fw-bold text-primary mb-1"></h6>
                                <p id="anuncioSubTexto2" class="small text-muted mb-0" style="white-space: pre-line;"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3 justify-content-end">
                    <button type="button" class="btn btn-primary px-4 fw-bold rounded-pill" data-bs-dismiss="modal">Entendido / Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALES DE FUNCIONALIDAD -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light border-0">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Iniciar sesión</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="loginMsg" class="alert d-none" role="alert"></div>
                    <form id="formLogin" autocomplete="on">
                        <div class="mb-3">
                            <label class="form-label" for="loginEmail">Correo</label>
                            <input type="email" class="form-control" id="loginEmail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="loginPass">Contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="loginPass" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleLoginPass">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-success w-100" type="submit">Entrar</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/olvido.html" class="text-info">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalMensajes" tabindex="-1" aria-labelledby="modalMensajesLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMensajesLabel">🔔 Notificaciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="contenidoModalMensajes">
                    Cargando...
                </div>
                <div class="modal-footer">
                    <a href="/mensajes.html" class="btn btn-primary">Ver mensajes</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Elegí cómo querés entrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>Tu correo está registrado como <strong>usuario</strong> y como <strong>profesional</strong>.
                        Elegí un rol
                        para continuar.</p>
                    <div class="d-grid gap-2">
                        <button id="btnRolUsuario" class="btn btn-outline-primary"><i class="bi bi-person"></i> Entrar
                            como
                            usuario</button>
                        <button id="btnRolProfesional" class="btn btn-outline-success"><i class="bi bi-briefcase"></i>
                            Entrar como
                            profesional</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- FOOTER ORIGINAL -->
    <footer class="bg-dark text-light pt-4 pb-3" style="margin-bottom: 0;">
        <style>
            @media (max-width: 768px) {
                footer {
                    margin-bottom: 0 !important;
                    padding-bottom: 90px !important;
                }
            }
        </style>
        <div class="container text-center">

            <!-- Redes sociales -->
            <div class="d-flex justify-content-center gap-4 mb-3 social-icons">
                <a href="https://www.facebook.com/profile.php?id=61586662526337" target="_blank"
                    aria-label="Facebook BuscoTec">
                    <i class="bi bi-facebook"></i>
                </a>

                <a href="https://www.instagram.com/buscotecarg/" target="_blank" aria-label="Instagram BuscoTec">
                    <i class="bi bi-instagram"></i>
                </a>

                <a href="https://www.tiktok.com/@buscotec?lang=es-419" target="_blank" aria-label="TikTok BuscoTec">
                    <i class="bi bi-tiktok"></i>
                </a>
            </div>

            <!-- Copyright -->
            <p class="mb-0 small opacity-75">
                &copy; 2025 BuscoTec. Todos los derechos reservados.
            </p>

        </div>
    </footer>

    <!-- SCRIPTS ORIGINALES DE INDEX FUNCIONANDO SOBRE EL HTML DE LANDING -->
    <script>
        function openMenu() {
            document.getElementById('mobile-menu').style.display = 'flex';
        }
        function closeMenu() {
            document.getElementById('mobile-menu').style.display = 'none';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- 🧩 Restaurar sesión en Safari/Android -->
    <script>
        (async () => {
            const id = localStorage.getItem('buscotec_user_id');
            const email = localStorage.getItem('buscotec_email');
            const role = localStorage.getItem('buscotec_role');

            // Si existen datos guardados, se los mandamos al backend para reactivar la sesión
            if (id && email) {
                console.log("🔄 Restaurando sesión:", { id, email, role });
                try {
                    await fetch('/backend/sesion_estable.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `user_id=${encodeURIComponent(id)}&email=${encodeURIComponent(email)}&role=${encodeURIComponent(role)}`
                    });
                } catch (err) {
                    console.warn("⚠️ No se pudo restaurar la sesión:", err);
                }
            }
        })();

        function initPasswordToggle() {
            const toggleBtn = document.getElementById('toggleLoginPass');
            const passInput = document.getElementById('loginPass');

            if (toggleBtn && passInput) {
                toggleBtn.addEventListener('click', function () {
                    // 1. Cambiar el tipo de input
                    const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passInput.setAttribute('type', type);

                    // 2. Cambiar el icono (Bootstrap Icons)
                    const icon = this.querySelector('i');
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                });
            }
        }

        // Llamar a la función dentro de tu DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            // ... tus otras funciones existentes ...
            initPasswordToggle();
        });
    </script>


    <script>
        // Constantes de Admin
        const ADMIN_ALLOWLIST = new Set(['oscarns@gmail.com', 'orticelli@gmail.com']);
        const ABS_LOGOUT_URL = "/backend/logout.php";
        const AUTH_KEYS = ['buscotec_role', 'buscotec_user_id', 'buscotec_nombre', 'buscotec_email', 'buscotec_last_auth_check'];


        // --- Funciones de Utilidad ---
        // Manejo general de visibilidad
        function toggleAll(selector, show) {
            document.querySelectorAll(selector).forEach(el => el.classList.toggle('d-none', !show));
        }

        // Verifica si el correo tiene acceso a Admin
        function canSeeAdmin(email, userId = null) {
            const cleanEmail = email ? String(email).trim().toLowerCase() : '';
            // Autorizar por Email O por ID de usuario (ID 10 es Oscar Nicolas, ID 11 es orticelli si aplica)
            const isAuthorized = ADMIN_ALLOWLIST.has(cleanEmail) || String(userId) === '10' || String(userId) === '11';

            if (isAuthorized) {
                // Log silenciado para limpiar consola (se llama cada 10 seg)
                // console.log("⭐ [Auth] Acceso Admin concedido para:", { email, userId });
            }
            return isAuthorized;
        }

        // Limpia toda la sesión local
        function clearSession() {
            try {
                AUTH_KEYS.forEach(k => localStorage.removeItem(k));
            } catch (e) {
                console.warn('[auth] No se pudo limpiar localStorage:', e);
            }
        }

        // Guarda datos de sesión local
        function saveSession(role, id, nombre, email) {
            try {
                if (role) localStorage.setItem('buscotec_role', role);
                if (id) localStorage.setItem('buscotec_user_id', String(id));
                if (nombre) localStorage.setItem('buscotec_nombre', nombre);
                if (email) localStorage.setItem('buscotec_email', email);
                localStorage.setItem('buscotec_last_auth_check', Date.now());
            } catch (e) {
                console.warn('[auth] Error guardando sesión local:', e);
            }
        }

        // Recupera la sesión almacenada en localStorage
        function getSavedSession() {
            try {
                return {
                    role: localStorage.getItem('buscotec_role'),
                    userId: localStorage.getItem('buscotec_user_id'),
                    nombre: localStorage.getItem('buscotec_nombre'),
                    email: localStorage.getItem('buscotec_email')
                };
            } catch (e) {
                console.warn('[auth] Error leyendo sesión local:', e);
                return { role: null, userId: null, nombre: null, email: null };
            }
        }

        // Llamadas seguras al backend que devuelven JSON
        async function fetchJSONSafe(url, options = {}) {
            try {
                const r = await fetch(url, { ...options, credentials: 'include', cache: 'no-store' });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const text = await r.text();
                return text ? JSON.parse(text) : null;
            } catch (e) {
                console.error('[fetchJSONSafe] Error al solicitar', url, e);
                return null;
            }
        }

        function updateUbicacionUI() {
            const mostrar = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
            const txt = mostrar ? '📍 Ubicación: Visible' : '🙈 Ubicación: Oculta';
            const bgStyle = mostrar ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.1)';
            const icoClass = mostrar ? 'bi bi-geo-alt-fill text-warning' : 'bi bi-eye-slash-fill text-light';

            ['lblUbicacion', 'lblUbicacionHero'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = txt;
            });

            ['icoUbicacion', 'icoUbicacionHero'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.className = icoClass;
            });

            ['btnToggleUbicacion', 'btnToggleUbicacionHero'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.background = bgStyle;
            });
        }

        function toggleUbicacionUsuario() {
            const estadoActual = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
            const nuevoEstado = !estadoActual;
            localStorage.setItem('buscotec_mostrar_ubicacion', nuevoEstado ? '1' : '0');
            updateUbicacionUI();

            const userId = localStorage.getItem('buscotec_user_id');
            const role = localStorage.getItem('buscotec_role');

            if (!nuevoEstado && userId && role) {
                // Si decide ocultar, borrar ubicación en vivo para evitar fijaciones obsoletas
                fetch('/backend/registrar_ubicacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&action=delete`
                }).catch(() => { });
            } else if (nuevoEstado && userId && role) {
                // Si decide mostrar, forzar geolocalización precisa e instantánea
                navigator.geolocation.getCurrentPosition((pos) => {
                    const { latitude, longitude } = pos.coords || {};
                    if (latitude && longitude) {
                        localStorage.setItem('buscotec_last_lat', latitude);
                        localStorage.setItem('buscotec_last_lng', longitude);
                        fetch('/backend/registrar_ubicacion.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&lat=${encodeURIComponent(latitude)}&lng=${encodeURIComponent(longitude)}`
                        }).then(() => {
                            if (typeof initAppMap === 'function') initAppMap();
                        }).catch(() => { });
                    }
                }, () => { }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                return;
            }

            if (typeof initAppMap === 'function') {
                initAppMap();
            }
        }

        // Actualiza el menú según el estado de sesión
        function updateNavUI(isLogged, nombre = 'Usuario', pending = 0, email = null, userIdParam = null) {
            updateUbicacionUI();
            const role = localStorage.getItem('buscotec_role');
            toggleAll('.auth', isLogged);
            toggleAll('.no-auth', !isLogged);
            toggleAll('.nav-auth-only', isLogged);
            toggleAll('.nav-no-auth-only', !isLogged);
            document.getElementById('notifBell')?.classList.toggle('d-none', !isLogged);

            const userMenu = document.getElementById('userMenu');
            if (userMenu) userMenu.textContent = `Hola, ${nombre}`;

            const userName = document.getElementById('userName');
            if (userName) userName.textContent = nombre;

            const appGreetingName = document.getElementById('appGreetingName');
            if (appGreetingName) appGreetingName.textContent = isLogged ? nombre : 'Visitante';

            const appGreeting = document.getElementById('appGreeting');
            if (appGreeting) {
                if (isLogged) {
                    if (role === 'profesional') {
                        appGreeting.innerHTML = `¡Hola ${nombre}! <br><span style="font-size: 0.9rem; font-weight: 400;">La app es totalmente GRATIS para vos hasta el 1 de Marzo de 2026.</span> ¿Qué buscás hoy?`;
                    } else {
                        appGreeting.textContent = `¡Hola ${nombre}! ¿Qué necesitás?`;
                    }
                } else {
                    appGreeting.textContent = '¡Hola! ¿Qué servicio necesitás hoy? ';
                }
            }

            const promoProf = document.getElementById('promoProfDesktop');
            if (promoProf) promoProf.classList.toggle('d-none', !isLogged || role !== 'profesional');

            const notif = document.getElementById('notifCount');
            if (notif) {
                notif.textContent = pending;
                notif.classList.toggle('d-none', pending <= 0);
            }

            // Si no llega por parámetro, intentamos buscarlo en memoria
            const actualId = userIdParam || localStorage.getItem('buscotec_user_id');

            // Mostrar Admin solo si el email o ID está autorizado
            toggleAll('.admin-link-only', canSeeAdmin(email, actualId));

            // 📍 Botón 'Estoy aquí': solo para profesionales
            const btnEstoyAquiWrap = document.getElementById('btnEstoyAquiWrap');
            if (btnEstoyAquiWrap) {
                const esProfesional = isLogged && role === 'profesional';
                btnEstoyAquiWrap.classList.toggle('d-none', !esProfesional);
                if (esProfesional) btnEstoyAquiWrap.style.display = 'flex';
            }

            // 🛡️ Solo para profesionales: Estado de cuenta
            const accountStatusLi = document.getElementById('accountStatusLi');
            if (accountStatusLi) {
                accountStatusLi.classList.toggle('d-none', !isLogged || role !== 'profesional');
            }
            toggleAll('.prof-only', isLogged && role === 'profesional');
        }


        // Envía ubicación al backend (automática al loguear)
        function postGeolocation(userId, role) {
            if (!('geolocation' in navigator) || !userId) return;
            const mostrarUbicacion = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
            if (!mostrarUbicacion) return;

            navigator.geolocation.getCurrentPosition((pos) => {
                const { latitude, longitude } = pos.coords || {};
                if (latitude && longitude) {
                    fetch('/backend/registrar_ubicacion.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&lat=${encodeURIComponent(latitude)}&lng=${encodeURIComponent(longitude)}`
                    }).catch(() => { });
                }
            }, () => { }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        }

        // 📍 Actualizar ubicación manual - botón "Estoy aquí" (solo profesionales)
        function actualizarUbicacion() {
            const btn = document.getElementById('btnEstoyAqui');
            if (!('geolocation' in navigator)) {
                alert('Tu dispositivo no soporta geolocalización.');
                return;
            }

            // Feedback: cargando
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span style="font-size:1.2rem;">⏳</span> Obteniendo ubicación...';
            }

            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    const { latitude: lat, longitude: lng } = pos.coords;
                    try {
                        const res = await fetch('/backend/actualizar_ubicacion.php', {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`
                        });
                        const data = await res.json();

                        if (data.ok) {
                            if (btn) {
                                btn.innerHTML = '<span style="font-size:1.2rem;">✅</span> ¡Ubicación actualizada!';
                                btn.style.background = 'linear-gradient(135deg,#198754,#157347)';
                            }
                        } else {
                            if (btn) btn.innerHTML = '<span style="font-size:1.2rem;">❌</span> Error al guardar';
                        }
                    } catch (e) {
                        if (btn) btn.innerHTML = '<span style="font-size:1.2rem;">❌</span> Error de red';
                    }

                    // Restaurar después de 3 segundos
                    setTimeout(() => {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<span style="font-size:1.2rem;">📍</span> Estoy aquí';
                            btn.style.background = 'linear-gradient(135deg,#0d6efd,#0a58ca)';
                        }
                    }, 3000);
                },
                (err) => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<span style="font-size:1.2rem;">📍</span> Estoy aquí';
                        if (err.code === 1) {
                            alert('Permiso de ubicación denegado. Habilitalo en la configuración de tu navegador.');
                        } else {
                            alert('No se pudo obtener tu ubicación. Intentá de nuevo.');
                        }
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        // Redirección según rol
        function goAfterRole(role) {
            // Recarga la página principal con la sesión ya guardada en localStorage.
            // Esto corrige el bug de iOS donde la app volvía al login al cerrar el modal.
            window.location.href = '/';
        }



        // --- Lógica de Modales y Roles ---

        function showRoleChooser(payload) {
            const roleModalEl = document.getElementById('roleModal');
            if (!roleModalEl) return;
            const roleModal = bootstrap.Modal.getOrCreateInstance(roleModalEl);

            const btnUsuario = document.getElementById('btnRolUsuario');
            const btnProf = document.getElementById('btnRolProfesional');

            // 🔹 Limpiar listeners anteriores para evitar duplicados
            btnUsuario.onclick = null;
            btnProf.onclick = null;

            btnUsuario.onclick = () => {
                const userId = payload.roles?.usuario || payload.userIdFallback;
                saveSession('usuario', userId, payload.nombre, payload.email);
                updateNavUI(true, payload.nombre, payload.pending || 0, payload.email);
                postGeolocation(userId, 'usuario');
                roleModal.hide();
                goAfterRole('usuario');

                // ✅ Inicializar push inmediatamente
                initPushAfterLogin();
            };

            btnProf.onclick = () => {
                const userId = payload.roles?.profesional || payload.userIdFallback;
                saveSession('profesional', userId, payload.nombre, payload.email);
                updateNavUI(true, payload.nombre, payload.pending || 0, payload.email);
                postGeolocation(userId, 'profesional');
                roleModal.hide();
                goAfterRole('profesional');

                // ✅ Inicializar push inmediatamente
                initPushAfterLogin();
            };

            roleModal.show();
        }


        async function initPushAfterLogin() {
            const userId = localStorage.getItem("buscotec_user_id");
            const role = localStorage.getItem("buscotec_role");

            if (!userId || !role) {
                console.warn("[push] Sin sesión activa, omitiendo.");
                return;
            }

            const waitForSDK = () => new Promise((resolve, reject) => {
                let tries = 0;
                const t = setInterval(() => {
                    tries++;
                    if (typeof window.webpushr === "function") { clearInterval(t); resolve(); }
                    if (tries > 50) { clearInterval(t); reject(new Error("Webpushr SDK no cargó")); }
                }, 2000);
            });

            const getSubscriberId = () => new Promise((resolve) => {
                try {
                    if (typeof webpushr !== 'function') { return resolve(""); }
                    webpushr('fetch_id', function (sid) {
                        if (sid && sid !== "0") {
                            resolve(String(sid));
                        } else {
                            webpushr('get', 'subscriber_id', function (sid2) {
                                resolve(sid2 ? String(sid2) : "");
                            });
                        }
                    });
                } catch (e) { resolve(""); }
            });

            try {
                await waitForSDK();
                let sid = "";
                for (let i = 0; i < 8; i++) {
                    sid = await getSubscriberId();
                    if (sid) break;
                    await new Promise(r => setTimeout(r, 2000));
                }
                if (!sid) { console.warn("[push] No se pudo obtener subscriberId"); return; }

                localStorage.setItem("webpushr_sid", sid);
                const payload = { user_id: parseInt(userId, 10), rol: role, webpushr_id: sid };
                const res = await fetch("/backend/guardar_suscripcion.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "include",
                    body: JSON.stringify(payload)
                });
                const j = await res.json().catch(() => ({}));
                if (j.ok) { console.log("[push] BBDD actualizada:", j); }
                else { console.warn("[push] Respuesta backend:", j); }
            } catch (err) {
                console.error("[push] Error:", err);
            }
        }




        // --- Autenticación y Carga Inicial ---

        async function initAuthUI() {
            const saved = getSavedSession();

            // 1. UI Optimista (Mostrar sesión guardada mientras carga)
            if (saved.userId) {
                updateNavUI(true, saved.nombre, 0, saved.email, saved.userId);
                console.log("🔹 [Auth] UI iniciada con datos locales:", saved);
            } else {
                updateNavUI(false);
            }

            // 2. Consultar al servidor
            console.log("🔹 [Auth] Verificando sesión con servidor...");
            const server = await fetchJSONSafe('/backend/get_perfil.php');
            console.log("🔹 [Auth] Respuesta servidor:", server);

            // 3. Análisis de la respuesta
            // Si server es null (error de red/json), NO deslogueamos. Asumimos offline o error temporal.
            if (!server) {
                console.warn("⚠️ [Auth] Falló la conexión con get_perfil. Mantenemos estado local.");
                return;
            }

            const logged = server.ok === true && (
                server.data?.usuario ||
                server.data?.profesional ||
                (server.data?.role && server.data?.id)
            );

            if (logged) {
                // --- SESIÓN VÁLIDA ---
                const d = server.data;
                const nombre = d.usuario?.nombre || d.profesional?.nombre || d.nombre || 'Usuario';
                const email = d.usuario?.email || d.profesional?.email || d.usuario?.correo || d.profesional?.correo || d.email || saved.email || null;
                const rolesArr = Array.isArray(d.roles) ? d.roles : [];
                const pending = Number.isFinite(d.pending_messages) ? d.pending_messages : 0;
                const userId = d.usuario?.id || d.profesional?.id || d.user_id || saved.userId; // Preferencia por datos frescos

                // Determinar rol actual
                const currentRole =
                    saved.role ||
                    rolesArr[0] ||
                    (d.role_ids?.usuario ? 'usuario' : (d.role_ids?.profesional ? 'profesional' : null));

                console.log("💎 [Auth] Email detectado:", email);
                updateNavUI(true, nombre, pending, email, userId);
                updateTopButtons(true, nombre);

                // Lógica de roles
                if (rolesArr.length > 1 && !saved.role) {
                    showRoleChooser({
                        roles: d.role_ids,
                        nombre,
                        pending,
                        email,
                        userIdFallback: userId
                    });
                } else if (userId && currentRole) {
                    saveSession(currentRole, userId, nombre, email);

                    // Solo enviar geo/push si ya tenemos rol definido
                    postGeolocation(userId, currentRole);
                    initPushAfterLogin();
                }

            } else {
                // --- NO LOGUEADO (Servidor dijo explícitamente ok:false) ---
                console.log("🔶 [Auth] El servidor indica que NO hay sesión activa.");

                // Solo limpiamos si NO teníamos sesión guardada, O si el servidor rechazó explícitamente una que creíamos tener.
                // Esto evita el parpadeo si localmente parecía que sí estábamos logueados.

                // Si teníamos datos locales pero el servidor dice "no", entonces sí es un logout real (o caducó).
                if (saved.userId) {
                    console.warn("🛑 [Auth] Sesión local expirada o inválida según servidor. Limpiando.");
                    clearSession();
                    updateNavUI(false);
                    updateTopButtons(false);
                    // Opcional: redirigir al home o mostrar aviso
                } else {
                    // Ya estábamos deslogueados, asegurar UI limpia
                    updateNavUI(false);
                    updateTopButtons(false);
                }
            }
        } // 🔧 <- ESTA LLAVE FALTABA (cierra la función initAuthUI)



        // --- Manejo del Estado del Servicio (Solo Profesionales) ---

        function actualizarBotonEstado(estado) {
            const btn = document.getElementById('estadoServicioBtn');
            if (!btn) return;

            if (estado == 1) {
                btn.innerHTML = `<i class="bi bi-toggle-on"></i> En servicio`;
                btn.classList.remove('btn-outline-light');
                btn.classList.add('btn-success');
            } else {
                btn.innerHTML = `<i class="bi bi-toggle-off"></i> Fuera de servicio`;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-light');
            }
        }

        function initEstadoServicio() {
            const btnEstado = document.getElementById('estadoServicioBtn');
            const rol = localStorage.getItem('buscotec_role');

            if (rol === 'profesional' && btnEstado) {
                btnEstado.classList.remove('d-none');

                // Obtener estado inicial del servidor
                fetch('/backend/get_estado_servicio.php', { credentials: 'include' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok && data.estado !== undefined) {
                            actualizarBotonEstado(data.estado);
                        }
                    }).catch(() => { });

                // Click para cambiar estado
                btnEstado.addEventListener('click', async () => {
                    try {
                        const res = await fetch('/backend/toggle_estado_servicio.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({})
                        });
                        const data = await res.json();
                        if (data.ok) {
                            actualizarBotonEstado(data.estado_servicio);
                        } else {
                            alert('No se pudo cambiar el estado.');
                        }
                    } catch (err) {
                        console.error('Error al cambiar estado', err);
                        alert('Error de red al cambiar estado.');
                    }
                });
            }
        }


        // --- Notificaciones ---

        async function updateNotifCount() {
            const badge = document.getElementById("notifCount");
            const bell = document.getElementById("notifBell");

            if (!badge || !bell) return;

            try {
                const res = await fetch("/backend/get_unread_count.php", { credentials: "include" });
                const data = await res.json();

                if (data.ok && data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.classList.remove("d-none");
                    bell.classList.remove("d-none");
                    bell.title = `Tenés ${data.unread} mensaje(s) sin leer`;
                    // También actualiza la UI de navegación
                    const saved = getSavedSession();
                    updateNavUI(!!saved.userId, saved.nombre, data.unread, saved.email);
                } else {
                    badge.classList.add("d-none");
                    // bell.classList.remove("d-none"); // Se maneja en updateNavUI
                    bell.title = "No tenés mensajes sin leer";
                    // También actualiza la UI de navegación
                    const saved = getSavedSession();
                    updateNavUI(!!saved.userId, saved.nombre, 0, saved.email);
                }
            } catch (err) {
                console.error("Error al actualizar notificaciones:", err);
            }
        }

        function initNotifBell() {
            // Refrescar cada 10 segundos
            setInterval(updateNotifCount, 10000);

            // Abrir el modal al hacer click en la campanita
            const bell = document.getElementById("notifBell");
            if (bell) {
                bell.addEventListener("click", async () => {
                    const contenedor = document.getElementById("contenidoModalMensajes");
                    const modalEl = document.getElementById("modalMensajes");
                    if (!contenedor || !modalEl) return;

                    contenedor.innerHTML = "Cargando...";

                    try {
                        const res = await fetch("/backend/get_unread_count.php", { credentials: "include" });
                        const data = await res.json();

                        if (data.ok && data.unread > 0) {
                            contenedor.innerHTML = `Tenés <strong>${data.unread}</strong> mensaje(s) sin leer.`;
                        } else {
                            contenedor.innerHTML = `No tenés mensajes sin leer.`;
                        }

                        const modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    } catch (err) {
                        console.error("Error al cargar mensajes:", err);
                        contenedor.innerHTML = "⚠️ Error al obtener mensajes.";
                    }
                });
            }

            // Fix para que no quede grisado al cerrar modalMensajes
            const modalMensajes = document.getElementById('modalMensajes');
            if (modalMensajes) {
                modalMensajes.addEventListener('hidden.bs.modal', function () {
                    document.body.classList.remove('modal-open');
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                });
            }
        }


        // --- Manejo de Formularios ---

        function initLogin() {
            const formLogin = document.getElementById('formLogin');
            if (!formLogin) return;

            formLogin.addEventListener('submit', async (e) => {
                e.preventDefault();

                const email = document.getElementById('loginEmail').value.trim();
                const password = document.getElementById('loginPass').value;
                const msg = document.getElementById('loginMsg');
                const loginModalEl = document.getElementById('loginModal');

                msg.className = 'alert d-none';
                msg.textContent = '';

                try {
                    const res = await fetch('/backend/login.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `correo=${encodeURIComponent(email)}&clave=${encodeURIComponent(password)}`
                    });

                    const raw = await res.text();
                    let data = null;
                    try { data = JSON.parse(raw); } catch (_) { console.error("No JSON", raw); }


                    if (!data?.success) {
                        msg.className = 'alert alert-danger';
                        msg.textContent = data?.message || 'Error al iniciar sesión';
                        return;
                    }

                    // Datos correctos
                    const roles = Array.isArray(data.roles) ? data.roles : [];
                    const nombre = data.nombre || 'Usuario';
                    const emailUser = data.email || email;
                    const pending = Number.isFinite(data.pending_messages) ? data.pending_messages : 0;
                    const userIdFallback = data.user_id;

                    // Cerrar modal login
                    if (loginModalEl) {
                        const loginModal = bootstrap.Modal.getOrCreateInstance(loginModalEl);
                        document.activeElement?.blur?.();
                        loginModal.hide();
                        // Opcional: limpiar backdrop manualmente
                        setTimeout(() => document.body.style = '', 300);
                    }

                    // Manejo de roles
                    if (roles.includes('usuario') && roles.includes('profesional')) {
                        showRoleChooser({ roles: data.role_ids, nombre, pending, email: emailUser, userIdFallback });
                    } else {
                        // ✅ Corregido para usuarios con un solo rol
                        const selectedRole = roles[0] || (data.role || 'usuario');
                        const roleIds = data.role_ids || {};
                        const roleId =
                            (roleIds[selectedRole]) ||
                            roleIds['profesional'] ||
                            roleIds['usuario'] ||
                            data.id ||
                            userIdFallback;

                        console.log("[login] Rol detectado:", selectedRole, "ID:", roleId);

                        saveSession(selectedRole, roleId, nombre, emailUser);
                        updateNavUI(true, nombre, pending, emailUser);
                        updateTopButtons(true, nombre);
                        postGeolocation(roleId, selectedRole);
                        initPushAfterLogin();
                        goAfterRole(selectedRole);

                        // ✅ Forzar refresco visual sin borrar caché
                        setTimeout(() => {
                            // Limpia modales colgados y repinta UI
                            document.body.style = '';
                            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                            // Re-render del navbar (saludo y botones)
                            updateTopButtons(true, nombre);
                            updateNavUI(true, nombre, pending, emailUser);
                        }, 600);

                    }

                } catch (err) {
                    console.error('Error en login:', err);
                    msg.className = 'alert alert-danger';
                    msg.textContent = 'Error al conectar con el servidor';
                }
            });
        }

        function initLogout() {
            document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
                e.preventDefault();

                try {
                    // 1️⃣ Llamar al backend para destruir sesión
                    const res = await fetch('/backend/logout.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { "Content-Type": "application/json" },
                        body: "{}"
                    });
                    const data = await res.json();
                    console.log("[logout] Backend:", data);

                    // 2️⃣ Borrar cualquier rastro local (incluye PWA)
                    localStorage.clear();
                    sessionStorage.clear();

                    // 3️⃣ Borrar cookies manualmente (en móvil y PWA)
                    document.cookie.split(";").forEach(c => {
                        document.cookie = c
                            .replace(/^ +/, "")
                            .replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/;SameSite=None;Secure");
                    });

                    // 4️⃣ Redirigir
                    window.location.href = "/";

                } catch (err) {
                    console.error("[logout] Error al cerrar sesión:", err);
                    alert("Error al cerrar sesión. Intentá nuevamente.");
                }
            });

            // ✅ También cerrar sesión desde el botón superior (navbar derecho)
            document.getElementById('logoutBtnTop')?.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('logoutBtn')?.click();
            });
        }



        // --- Lógica de Categorías y URL ---



        function checkUrlMessages() {
            const params = new URLSearchParams(window.location.search);
            const alerta = document.getElementById('alerta');
            if (!alerta) return;

            if (params.get('msg') === 'verificado') {
                alerta.textContent = '✅ Email verificado correctamente. Ya podés iniciar sesión.';
                alerta.classList.remove('d-none', 'alert-danger'); alerta.classList.add('alert-success');
                history.replaceState(null, '', window.location.pathname);
            }
            if (params.get('err') === 'forbidden') {
                alerta.textContent = 'Acceso denegado. Iniciá sesión con un correo autorizado para ver Admin.';
                alerta.classList.remove('d-none', 'alert-success'); alerta.classList.add('alert-danger');
                history.replaceState(null, '', window.location.pathname);
            }
        }
        function initCategorias() {
            const select = document.getElementById("categoriaSelect");
            const catsGrid = document.getElementById("cats-grid");
            const carouselInner = document.getElementById("appCategoriesCarouselInner");
            const carouselIndicators = document.getElementById("appCategoriesIndicators");

            fetch("/backend/get_categorias.php", { cache: "no-store" })
                .then(res => {
                    if (!res.ok) throw new Error("Error HTTP " + res.status);
                    return res.json();
                })
                .then(data => {
                    const categorias = Array.isArray(data.items) ? data.items : [];

                    if (categorias.length === 0) {
                        if (select) select.innerHTML = "<option value=''>No hay categorías disponibles</option>";
                        if (catsGrid) catsGrid.innerHTML = "<div class='text-muted small'>No hay categorías disponibles</div>";
                        return;
                    }

                    if (select) {
                        select.innerHTML = "<option value=''>Selecciona una categoría</option>";
                        categorias.forEach(cat => {
                            const opt = document.createElement("option");
                            opt.value = cat.id;
                            opt.textContent = cat.nombre;
                            select.appendChild(opt);
                        });
                    }

                    // Mapas de íconos y colores pasteles idénticos a la app móvil
                    const iconMapPastel = {
                        'electricista': { icon: 'bi-lightning-fill', bg: '#FEF3C7', color: '#D97706' },
                        'plomero': { icon: 'bi-droplet-fill', bg: '#E0F2FE', color: '#0284C7' },
                        'cerrajero': { icon: 'bi-key-fill', bg: '#F3E8FF', color: '#9333EA' },
                        'carpintero': { icon: 'bi-hammer', bg: '#F5EBE0', color: '#854D0E' },
                        'gasista': { icon: 'bi-fire', bg: '#FEE2E2', color: '#DC2626' },
                        'albañil': { icon: 'bi-tools', bg: '#E2E8F0', color: '#475569' },
                        'jardinero': { icon: 'bi-tree-fill', bg: '#DCFCE7', color: '#16A34A' },
                        'mantenimiento': { icon: 'bi-wrench', bg: '#F3F4F6', color: '#4B5563' },
                        'pintor': { icon: 'bi-paint-bucket', bg: '#FCE4EC', color: '#DB2777' },
                        'flete': { icon: 'bi-truck', bg: '#E8EAF6', color: '#4F46E5' },
                        'limpieza': { icon: 'bi-stars', bg: '#F0F4C3', color: '#65A30D' },
                        'mecanico': { icon: 'bi-gear-wide-connected', bg: '#F5F5F5', color: '#334155' },
                        // Nuevas mapeos para populares
                        'tecnico': { icon: 'bi-pc-display', bg: '#E0F2FE', color: '#0369A1' },
                        'pc': { icon: 'bi-pc-display', bg: '#E0F2FE', color: '#0369A1' },
                        'computacion': { icon: 'bi-pc-display', bg: '#E0F2FE', color: '#0369A1' },
                        'multitarea': { icon: 'bi-briefcase-fill', bg: '#FAF5FF', color: '#7E22CE' },
                        'casual': { icon: 'bi-briefcase-fill', bg: '#FAF5FF', color: '#7E22CE' },
                        'herrero': { icon: 'bi-shield-shaded', bg: '#FFF1F2', color: '#E11D48' },
                        'herreria': { icon: 'bi-shield-shaded', bg: '#FFF1F2', color: '#E11D48' },
                        'abogado': { icon: 'bi-journal-bookmark-fill', bg: '#FEF3C7', color: '#B45309' },
                        'contador': { icon: 'bi-calculator-fill', bg: '#D1FAE5', color: '#047857' }
                    };

                    // 1. Grilla Pastel Estilo App (#cats-grid): Mostrar 8 principales (2 filas)
                    if (catsGrid) {
                        catsGrid.innerHTML = "";
                        const topCategorias = categorias.slice(0, 8);
                        topCategorias.forEach(cat => {
                            const norm = cat.nombre.toLowerCase();
                            let match = { icon: 'bi-grid-fill', bg: '#F1F5F9', color: '#0d6efd' };
                            for (const k in iconMapPastel) {
                                if (norm.includes(k)) { match = iconMapPastel[k]; break; }
                            }
                            const a = document.createElement("a");
                            a.href = `categoria.php?id=${cat.id}&nombre=${encodeURIComponent(cat.nombre)}`;
                            a.className = "app-cat-card-item";
                            a.innerHTML = `
                                <div class="app-cat-icon-wrapper" style="background-color: ${match.bg}; color: ${match.color};">
                                    <i class="bi ${match.icon}"></i>
                                </div>
                                <span class="app-cat-label">${cat.nombre}</span>
                            `;
                            catsGrid.appendChild(a);
                        });
                    }

                    // 2. Carrusel App (Móvil si está disponible)
                    if (carouselInner) {
                        carouselInner.innerHTML = "";
                        if (carouselIndicators) carouselIndicators.innerHTML = "";

                        const itemsPerSlide = 8;
                        const slidesCount = Math.ceil(categorias.length / itemsPerSlide);

                        for (let i = 0; i < slidesCount; i++) {
                            if (carouselIndicators) {
                                const btn = document.createElement("button");
                                btn.type = "button";
                                btn.dataset.bsTarget = "#appCategoriesCarousel";
                                btn.dataset.bsSlideTo = i;
                                if (i === 0) { btn.className = "active"; btn.ariaCurrent = "true"; }
                                carouselIndicators.appendChild(btn);
                            }

                            const slide = document.createElement("div");
                            slide.className = "carousel-item" + (i === 0 ? " active" : "");

                            const gridDiv = document.createElement("div");
                            gridDiv.className = "app-carousel-grid";

                            const start = i * itemsPerSlide;
                            const end = Math.min(start + itemsPerSlide, categorias.length);

                            for (let j = start; j < end; j++) {
                                const cat = categorias[j];
                                const norm = cat.nombre.toLowerCase();
                                let match = { icon: 'bi-gear-fill', bg: '#f8f9fa', color: '#333' };
                                for (const k in iconMapPastel) {
                                    if (norm.includes(k)) { match = iconMapPastel[k]; break; }
                                }

                                const item = document.createElement("a");
                                item.href = `categoria.php?id=${cat.id}&nombre=${encodeURIComponent(cat.nombre)}`;
                                item.className = "category-item";
                                item.innerHTML = `
                                  <div class="category-icon-box" style="background-color: ${match.bg}; color: ${match.color};">
                                    <i class="bi ${match.icon}"></i>
                                  </div>
                                  <span>${cat.nombre}</span>
                                `;
                                gridDiv.appendChild(item);
                            }
                            slide.appendChild(gridDiv);
                            carouselInner.appendChild(slide);
                        }
                    }

                    // 3. Inicializar buscadores (Desktop, Hero App y general)
                    initUnifiedSearch(categorias, "buscadorCategoria", "listaSugerencias");
                    initUnifiedSearch(categorias, "appSearchInput", "appSugerencias");
                    initUnifiedSearch(categorias, "appHeroSearchInput", "appHeroSugerencias");
                })
                .catch(err => {
                    console.error("Error al cargar categorías:", err);
                    if (select) select.innerHTML = "<option value=''>Error al cargar categorías</option>";
                });

            if (select) {
                select.addEventListener("change", () => {
                    const categoriaId = select.value;
                    if (categoriaId) {
                        const categoriaNombre = select.options[select.selectedIndex].textContent;
                        window.location.href = `categoria.php?id=${categoriaId}&nombre=${encodeURIComponent(categoriaNombre)}`;
                    }
                });
            }
        }

        function initUnifiedSearch(categorias, inputId, listId) {
            const input = document.getElementById(inputId);
            const lista = document.getElementById(listId);
            if (!input || !lista) return;

            input.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    const texto = input.value.toLowerCase().trim();
                    if (!texto) return window.location.href = 'categoria.php';
                    const catMatch = categorias.find(c => c.nombre.toLowerCase() === texto);
                    if (catMatch) {
                        window.location.href = `categoria.php?id=${catMatch.id}&nombre=${encodeURIComponent(catMatch.nombre)}`;
                    } else {
                        // General redirect with no specific category id
                        window.location.href = `categoria.php?nombre=` + encodeURIComponent("Buscando: " + input.value);
                    }
                }
            });


            input.addEventListener("input", () => {
                const texto = input.value.toLowerCase().trim();
                lista.innerHTML = "";

                if (!texto) {
                    lista.classList.add("d-none");
                    return;
                }

                const coincidencias = categorias.filter(cat =>
                    cat.nombre.toLowerCase().includes(texto)
                );

                if (coincidencias.length === 0) {
                    lista.innerHTML = `<li class="list-group-item text-muted small">Sin coincidencias</li>`;
                } else {
                    coincidencias.forEach(cat => {
                        const li = document.createElement("li");
                        li.className = "list-group-item list-group-item-action";
                        li.textContent = cat.nombre;
                        li.style.cursor = "pointer";
                        
                        // Usar mousedown para ganar la carrera al evento blur en computadoras
                        li.addEventListener("mousedown", (e) => {
                            e.preventDefault();
                            window.location.href = `categoria.php?id=${cat.id}&nombre=${encodeURIComponent(cat.nombre)}`;
                        });
                        
                        lista.appendChild(li);
                    });
                }
                lista.classList.remove("d-none");
            });

            input.addEventListener("blur", () => {
                setTimeout(() => lista.classList.add("d-none"), 200);
            });
        }

        // --- Lógica del Mapa en la App ---
        let appMap = null;
        let appMarkers = [];

        async function initAppMap() {
            const container = document.getElementById('mapContainerApp');
            if (!container) return;

            if (appMap) {
                appMap.remove();
                appMap = null;
            }

            const mostrarUbicacion = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';

            // Ubicación por defecto (Bariloche centro)
            let userPos = { lat: -41.13437, lng: -71.30822 };
            let esUbicacionReal = false;

            // Intentar obtener ubicación real de alta precisión GPS si el usuario la mantiene activa
            if (mostrarUbicacion) {
                try {
                    const pos = await new Promise((res, rej) => {
                        navigator.geolocation.getCurrentPosition(res, rej, {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        });
                    });
                    const checkLat = pos.coords.latitude;
                    const checkLng = pos.coords.longitude;

                    userPos = { lat: checkLat, lng: checkLng };
                    localStorage.setItem('buscotec_last_lat', String(checkLat));
                    localStorage.setItem('buscotec_last_lng', String(checkLng));
                    esUbicacionReal = true;

                    // Si hay usuario logueado, registrar ubicación en servidor
                    const userId = localStorage.getItem('buscotec_user_id');
                    const role = localStorage.getItem('buscotec_role');
                    if (userId && role && typeof postGeolocation === 'function') {
                        postGeolocation(userId, role);
                    }
                } catch (e) {
                    console.warn("No se pudo obtener posición GPS en vivo, buscando última registrada.");
                    const savedLat = parseFloat(localStorage.getItem('buscotec_last_lat'));
                    const savedLng = parseFloat(localStorage.getItem('buscotec_last_lng'));
                    if (!isNaN(savedLat) && !isNaN(savedLng)) {
                        userPos = { lat: savedLat, lng: savedLng };
                        esUbicacionReal = true;
                    }
                }
            }

            appMap = L.map('mapContainerApp', { zoomControl: false }).setView([userPos.lat, userPos.lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(appMap);

            // Marcador de usuario (punto azul brillante con borde blanco) solo si ubicación está visible
            if (mostrarUbicacion) {
                const uMarker = L.circleMarker([userPos.lat, userPos.lng], {
                    radius: 9, fillOpacity: 0.95, color: '#ffffff', weight: 3, fillColor: '#1877f2'
                }).addTo(appMap);
                
                uMarker.bindPopup(esUbicacionReal ? "<b>📍 Tu ubicación GPS real</b>" : "<b>📍 Bariloche (Ubicación por defecto)</b>");
            }

            // Cargar profesionales cercanos calculando distancia desde la posición real
            loadNearbyProfessionals(userPos.lat, userPos.lng);
        }

        async function loadNearbyProfessionals(lat, lng) {
            try {
                const res = await fetch(`/backend/get_profesionales_cercanos.php?lat=${lat}&lng=${lng}`);
                const data = await res.json();

                const carousel = document.getElementById('profCarousel');
                if (!carousel) return;

                if (!data.ok) return;

                // Limpiar carrusel previo
                carousel.innerHTML = "";
                appMarkers.forEach(m => appMap.removeLayer(m));
                appMarkers = [];

                const customIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: "<div style='background-color:#1877f2; width:12px; height:12px; border-radius:50%; border:2px solid white; box-shadow:0 0 5px rgba(0,0,0,0.3);'></div>",
                    iconSize: [12, 12],
                    iconAnchor: [6, 6]
                });

                    const createCard = (p, idx) => {
                        const isDisp = p.disponible;
                        const card = document.createElement('div');
                        card.className = "prof-card-mini";
                        const fotoUrl = p.foto ? p.foto : 'img/logo_web.png';

                        let distTxt = 'Bariloche';
                        const valDist = (p.dist_km !== undefined && p.dist_km !== null) ? p.dist_km : (p.distancia !== undefined ? p.distancia : null);
                        if (valDist !== null && !isNaN(valDist) && valDist > 0) {
                            distTxt = `a ${valDist} km`;
                        } else if (p.ciudad) {
                            distTxt = p.ciudad;
                        }

                        card.innerHTML = `
            <img src="${fotoUrl}" alt="${p.nombre}" class="prof-card-img" onerror="this.onerror=null; this.src='img/logo_web.png';">
            <div class="prof-card-info">
              <p class="prof-card-name">${p.nombre} ${p.apellido || ''}</p>
              <p class="prof-card-cat">${p.categoria}</p>
              <p class="small text-muted mb-0"><i class="bi bi-geo-alt"></i> ${distTxt}</p>
            </div>
          `;
                        card.onclick = () => window.location.href = `perfil_profesional.html?id=${p.id}`;
                        return card;
                    };

                const profesionales = data.profesionales;
                if (profesionales.length > 0) {
                    carousel.classList.remove('d-none');

                    // 1. Agregar originales
                    profesionales.forEach((p, idx) => {
                        // Marcador (solo para originales)
                        if (p.lat && p.lng) {
                            const marker = L.marker([p.lat, p.lng], { icon: customIcon }).addTo(appMap);
                            // Al tocar pin, scrollear al primero (idx)
                            marker.on('click', () => {
                                carousel.scrollTo({ left: 0, behavior: 'smooth' });
                            });
                            appMarkers.push(marker);
                        }
                        carousel.appendChild(createCard(p, idx));
                    });

                    // 2. CLONAR para efecto infinito (si hay suficientes para scrollear)
                    if (profesionales.length >= 2) {
                        profesionales.forEach((p, idx) => {
                            const clone = createCard(p, idx);
                            clone.classList.add('clone-card');
                            carousel.appendChild(clone);
                        });
                    }

                    // Iniciar auto-scroll continuo 360 siempre
                    startAutoScrollProfessionals(profesionales.length);
                }

            } catch (e) {
                console.error("Error cargando profesionales para el mapa app", e);
            }
        }

        let profScrollInterval = null;

        function startAutoScrollProfessionals(originalCount) {
            const container = document.getElementById('profCarousel');
            if (!container) return;
            if (originalCount < 2) return; // No scrollear si hay muy pocos

            if (profScrollInterval) clearInterval(profScrollInterval);

            profScrollInterval = setInterval(() => {
                const card = container.firstElementChild;
                if (!card) return;

                const gap = 12;
                const cardWidth = card.offsetWidth + gap;

                // Ancho total de los ORIGINALES
                const originalsWidth = cardWidth * originalCount;

                // Hacemos scroll
                container.scrollBy({ left: cardWidth, behavior: 'smooth' });

                // Verificamos si pasamos el punto de corte para reiniciar (scroll infinito)
                // Usamos un timeout para esperar que termine el scroll smooth
                setTimeout(() => {
                    if (container.scrollLeft >= originalsWidth) {
                        // Volver al principio SIN animación (salto invisible)
                        container.scrollTo({ left: 0, behavior: 'auto' });
                    }
                }, 600); // 600ms es aprox lo que tarda el smooth scroll

            }, 3500);

            container.addEventListener('touchstart', () => clearInterval(profScrollInterval), { passive: true });
        }







        async function cargarModalProgramado() {
            try {
                const r = await fetch('/backend/get_modales_activos.php', { cache: 'no-store' });
                const data = await r.json();

                if (data.ok && data.modal) {
                    const m = data.modal;
                    const seenKey = 'modal_anuncio_visto_' + m.id;
                    if (sessionStorage.getItem(seenKey)) {
                        return;
                    }

                    const mainTitle = document.getElementById('anuncioModalTituloMain');
                    const subTitle = document.getElementById('anuncioTituloSub');
                    const textMain = document.getElementById('anuncioTextoMain');

                    if (mainTitle) mainTitle.textContent = m.titulo || 'Novedades';
                    if (subTitle) subTitle.textContent = m.titulo || '';
                    if (textMain) textMain.textContent = m.texto || '';

                    const mainImgCont = document.getElementById('anuncioMainImgContainer');
                    const mainImg = document.getElementById('anuncioMainImg');
                    if (m.imagen_principal && mainImg && mainImgCont) {
                        mainImg.src = m.imagen_principal;
                        mainImgCont.classList.remove('d-none');
                    } else if (mainImgCont) {
                        mainImgCont.classList.add('d-none');
                    }

                    let hasSub = false;
                    const col1 = document.getElementById('anuncioSubCol1');
                    const col2 = document.getElementById('anuncioSubCol2');

                    if ((m.sub_titulo_1 || m.sub_texto_1 || m.sub_imagen_1) && col1) {
                        hasSub = true;
                        col1.classList.remove('d-none');
                        const t1 = document.getElementById('anuncioSubTitulo1');
                        const txt1 = document.getElementById('anuncioSubTexto1');
                        const img1 = document.getElementById('anuncioSubImg1');
                        if (t1) t1.textContent = m.sub_titulo_1 || '';
                        if (txt1) txt1.textContent = m.sub_texto_1 || '';
                        if (m.sub_imagen_1 && img1) {
                            img1.src = m.sub_imagen_1;
                            img1.classList.remove('d-none');
                        } else if (img1) {
                            img1.classList.add('d-none');
                        }
                    }

                    if ((m.sub_titulo_2 || m.sub_texto_2 || m.sub_imagen_2) && col2) {
                        hasSub = true;
                        col2.classList.remove('d-none');
                        const t2 = document.getElementById('anuncioSubTitulo2');
                        const txt2 = document.getElementById('anuncioSubTexto2');
                        const img2 = document.getElementById('anuncioSubImg2');
                        if (t2) t2.textContent = m.sub_titulo_2 || '';
                        if (txt2) txt2.textContent = m.sub_texto_2 || '';
                        if (m.sub_imagen_2 && img2) {
                            img2.src = m.sub_imagen_2;
                            img2.classList.remove('d-none');
                        } else if (img2) {
                            img2.classList.add('d-none');
                        }
                    }

                    const subGrid = document.getElementById('anuncioSubGrid');
                    if (hasSub && subGrid) {
                        subGrid.classList.remove('d-none');
                    }

                    sessionStorage.setItem(seenKey, '1');

                    setTimeout(() => {
                        const modalEl = document.getElementById('modalAnuncioProgramado');
                        if (modalEl) {
                            const bsModal = new bootstrap.Modal(modalEl);
                            bsModal.show();
                        }
                    }, 1200);
                }
            } catch (e) {
                console.warn('Error cargando modal programado:', e);
            }
        }

        // --- Inicialización Principal ---

        document.addEventListener('DOMContentLoaded', () => {
            // Detección móvil simple
            if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                document.body.classList.add('is-mobile', 'app-view');
            }

            initAuthUI();
            initLogin();
            initLogout();
            initNotifBell();
            initCategorias();
            checkUrlMessages();

            // Inicializar Mapa y Profesionales cerca
            initAppMap();

            // Consultar si hay modales programados activos
            cargarModalProgramado();



            // PWA Service Worker (cache offline) — WebPushr registra el suyo propio via 'sw' en el setup
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => { }));
            }
        });

        // 🚑 FIX ANDROID BACK-BUTTON: Forzar recarga si volvemos atrás
        window.addEventListener('pageshow', (event) => {
            // Si la página viene de la caché "atrás/adelante" (bfcache)
            if (event.persisted) {
                console.log("🔄 Restaurado desde caché (back button), recargando sesión...");
                window.location.reload();
            }
        });
    </script>


    <script>
        fetch('/backend/get_perfil.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                // ✅ Si necesitás ver la respuesta en consola:
                console.log("Perfil obtenido:", data);
            })
            .catch(err => console.error("Error verificando sesión:", err));
    </script>

    <!-- MODAL para mostrar Player ID -->
    <div class="modal fade" id="modalPlayerId" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-primary shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">📡 Player ID Detectado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="playerIdText" class="fw-bold text-center fs-5">Cargando Player ID...</p>
                    <p class="text-muted small text-center mb-0">
                        Este ID identifica tu dispositivo para recibir notificaciones push.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>



    <script>
        /*
        // 🔍 Mostrar Player ID actual de Webpushr en un modal
        document.addEventListener("DOMContentLoaded", function() {
          try {
            // Espera a que Webpushr esté disponible
            let check = setInterval(() => {
              if (typeof webpushr !== "undefined") {
                clearInterval(check);
                webpushr('get', 'subscriber_id', function (sid) {
                  const modal = new bootstrap.Modal(document.getElementById('modalPlayerId'));
                  const pidEl = document.getElementById('playerIdText');
        
                  if (sid && sid !== "undefined" && sid !== "") {
                    pidEl.textContent = "🆔 Player ID actual: " + sid;
                  } else {
                    pidEl.textContent = "⚠️ No se detectó Player ID en este dispositivo.";
                  }
        
                  // Mostrar modal automáticamente
                  modal.show();
                });
              }
            }, 1000);
          } catch (e) {
            console.error("Error mostrando Player ID:", e);
          }
        });
        */


        async function rehydrateSessionIfNeeded() {
            try {
                const jwt = localStorage.getItem('jwt_token') || '';
                const headers = jwt ? { 'Authorization': 'Bearer ' + jwt } : {};
                const r = await fetch('/backend/session_from_jwt.php', { credentials: 'include', headers });
                const j = await r.json();
                return !!j.ok;
            } catch (e) {
                return false;
            }
        }

        async function fetchWithRehydrate(input, init = {}) {
            let res = await fetch(input, { credentials: 'include', ...init });
            if (res.status !== 401) return res;
            const ok = await rehydrateSessionIfNeeded();
            if (!ok) return res;
            return await fetch(input, { credentials: 'include', ...init });
        }


    </script>

    <script>
        async function cargarContadorNotificaciones() {
            try {
                const r = await fetch('/backend/get_unread_count.php', { credentials: 'include' });
                const j = await r.json();
                const bell = document.getElementById('notifBell');
                const notif = document.getElementById('notifCount');

                if (j.ok) {
                    const total = parseInt(j.no_leidos ?? 0);
                    notif.textContent = total;

                    // 🔽 Mostrar campanita solo si hay mensajes no leídos
                    if (total > 0) {
                        notif.classList.remove('d-none');
                        bell.classList.remove('d-none');
                    } else {
                        notif.classList.add('d-none');
                    }
                }
            } catch (e) {
                console.warn('Error al obtener contador', e);
            }
        }

        document.addEventListener('DOMContentLoaded', cargarContadorNotificaciones);

        // 🩵 Mantener la cookie de sesión viva en Android / PWA
        function keepSessionAlive() {
            const id = localStorage.getItem('buscotec_user_id');
            const email = localStorage.getItem('buscotec_email');
            const role = localStorage.getItem('buscotec_role');
            if (!id || !email) return;

            fetch('/backend/sesion_estable.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(id)}&email=${encodeURIComponent(email)}&role=${encodeURIComponent(role)}`
            }).catch(() => { });
        }

        // 🔁 Ping cada 10 s (mientras la página esté activa)
        setInterval(keepSessionAlive, 10000);

        // 🚀 Ejecutar también al recuperar foco (app vuelve a primer plano)
        window.addEventListener('focus', keepSessionAlive);

        // 🧠 Rehidratar sesión en Safari si borra la cookie
        async function rehydrateIfNeeded() {
            const jwt = localStorage.getItem('bt_jwt');
            if (!jwt) return;
            try {
                const res = await fetch('/backend/session_from_jwt.php', {
                    method: 'GET',
                    credentials: 'include',
                    headers: { 'Authorization': 'Bearer ' + jwt }
                });
                const data = await res.json();
                if (data.ok) {
                    console.log("✅ Sesión restaurada desde JWT:", data.data.email);
                } else {
                    console.warn("⚠️ Token inválido:", data.error);
                }
            } catch (err) {
                console.error("Error rehidratando sesión:", err);
            }
        }

        // 🔁 Llamar cada 15 s por Safari (que borra cookies en memoria)
        setInterval(rehydrateIfNeeded, 15000);

        // 🔄 Mostrar u ocultar botones del navbar superior
        function updateTopButtons(isLogged, nombre = '') {
            const loginBtn = document.getElementById('loginBtnTop');
            const registerBtn = document.getElementById('registerBtnTop');
            const logoutBtn = document.getElementById('logoutBtnTop');
            const greeting = document.getElementById('userGreeting');
            const nameSpan = document.getElementById('userName');

            if (nameSpan) nameSpan.textContent = nombre || '';

            if (isLogged) {
                loginBtn?.classList.add('d-none');
                registerBtn?.classList.add('d-none');
                logoutBtn?.classList.remove('d-none');
                greeting?.classList.remove('d-none');
            } else {
                loginBtn?.classList.remove('d-none');
                registerBtn?.classList.remove('d-none');
                logoutBtn?.classList.add('d-none');
                greeting?.classList.add('d-none');
            }
        }
    </script>

    <!-- AVISO PWA MEJORADO CON ESTILOS INLINE -->
    <div id="ios-prompt" class="ios-install-prompt"
        style="display: none; position: fixed !important; bottom: 20px !important; left: 50% !important; transform: translateX(-50%) !important; width: 95% !important; max-width: 400px !important; background: rgba(255, 255, 255, 0.98) !important; backdrop-filter: blur(10px) !important; -webkit-backdrop-filter: blur(10px) !important; padding: 20px !important; border-radius: 20px !important; box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important; z-index: 20000 !important; border: 1px solid rgba(0,0,0,0.1) !important; font-family: -apple-system, system-ui, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;">
        <div class="close-prompt" onclick="closePrompt()"
            style="position: absolute !important; top: 10px !important; right: 15px !important; font-size: 20px !important; color: #888 !important; cursor: pointer !important;">
            ✕</div>
        <div class="prompt-content"
            style="display: flex !important; flex-direction: column !important; gap: 12px !important;">
            <div class="prompt-header"
                style="display: flex !important; align-items: center !important; gap: 15px !important;">
                <img src="img/icons/icon-192.png"
                    style="width: 45px !important; height: 45px !important; border-radius: 10px !important; box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;">
                <div class="prompt-text">
                    <h4
                        style="margin: 0 !important; font-size: 16px !important; font-weight: 800 !important; color: #1877f2 !important;">
                        Instalar BuscoTec</h4>
                    <p
                        style="margin: 2px 0 0 !important; font-size: 13px !important; color: #444 !important; line-height: 1.2 !important;">
                        Es necesario para recibir notificaciones del profesional o del usuario.</p>
                </div>
            </div>
            <div class="prompt-instruction"
                style="margin: 5px 0 !important; font-size: 14px !important; color: #333 !important; line-height: 1.4 !important; text-align: center !important;">
                Toca el botón <strong>Compartir</strong> (abajo al centro) y luego <strong>"Agregar al inicio"</strong>.
            </div>
            <img src="ios-guide.png"
                style="width: 100% !important; height: auto !important; border-radius: 12px !important; border: 1px solid #eee !important; display: block !important;">
        </div>
    </div>

    <script>
        function closePrompt() {
            document.getElementById('ios-prompt').style.setProperty('display', 'none', 'important');
        }

        window.addEventListener('load', function () {
            // --- 📍 GEOLOCALIZACIÓN DINÁMICA DE CIUDAD ---
            async function updateDynamicCity() {
                try {
                    // 1. Intentar por IP (rápido y sin permisos)
                    const resCity = await fetch('https://ipapi.co/json/');
                    const dataCity = await resCity.json();

                    if (dataCity && dataCity.city) {
                        applyCity(dataCity.city);
                        return;
                    }
                } catch (e) {
                    console.warn("Fallo detección ciudad por IP, probando GPS...");
                }

                // 2. Plan B: GPS (requiere permiso)
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(async (pos) => {
                        try {
                            const { latitude, longitude } = pos.coords;
                            const resGeo = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`);
                            const dataGeo = await resGeo.json();
                            const city = dataGeo.address.city || dataGeo.address.town || dataGeo.address.village;
                            if (city) applyCity(city);
                        } catch (err) { }
                    }, null, { timeout: 5000 });
                }
            }

            function applyCity(cityName) {
                document.querySelectorAll('.dynamic-city').forEach(el => {
                    el.textContent = cityName;
                });
                const badgeCity = document.getElementById('badge-city');
                if (badgeCity) badgeCity.innerHTML = `<i class="bi bi-geo-alt-fill"></i> ${cityName}`;
                console.log("📍 Ubicación activa:", cityName);
            }

            async function loadConfiguredRegion() {
                try {
                    const r = await fetch('backend/get_ajustes_cobro.php');
                    const j = await r.json();
                    if (j.ok && j.data && j.data.region_cobertura) {
                        applyCity(j.data.region_cobertura);
                    }
                } catch (e) { }
            }

            loadConfiguredRegion();

            setTimeout(function () {
                var p = document.getElementById('ios-prompt');
                // 🔍 Detección Senior: Solo iPhone/iPad y que NO esté instalada la PWA
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

                if (p && isIOS && !isStandalone) {
                    p.style.setProperty('display', 'block', 'important');
                    console.log("Aviso PWA activado para iPhone.");
                } else {
                    console.log("Aviso PWA ignorado (no es iOS o ya está instalada).");
                }
            }, 2000);
        });
    </script>
</body>

</html>