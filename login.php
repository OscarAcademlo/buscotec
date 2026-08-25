<?php
// BuscoTec - login.php - Actualizado: 16:30
require_once __DIR__ . '/backend/session_boot.php';

// --- Redirección agresiva a WWW (Previene Error 4 de Webpushr y conflictos de sesión) ---
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'www.') === false && strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
    header("Location: https://www.buscotec.com.ar" . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <script>
        // Redirección forzada a WWW (Mejorado para Safari/iOS)
        if (window.location.hostname === 'buscotec.com.ar') {
            window.location.replace(window.location.href.replace('buscotec.com.ar', 'www.buscotec.com.ar'));
        }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BuscoTec</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/img/icons/icon-192.png">
    <link rel="stylesheet" href="index.css?v=9.4">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        /* ============================================
   1) RESET GENERAL
   ============================================ */
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #1877f2;
            /* azul Facebook */
            padding-top: 56px;
            /* espacio del navbar fijo */
        }



        /* ============================================
   2) CONTENIDO PRINCIPAL
   ============================================ */
        main {
            flex: 1 0 auto;
        }



        /* ============================================
   3) HERO - COMPATIBLE MAC / SAFARI / TABLETS
   ============================================ */

        .hero-bg {
            position: relative;
            width: 100%;
            background: url('img/logo.png') center center / cover no-repeat;

            /* Evitar bandas azules en Mac, iPad, iOS */
            background-attachment: scroll !important;

            display: grid;
            place-items: center;

            padding: calc(56px + 20px) 1rem 40px;
            /* debajo del navbar */
            margin: 0;

            min-height: auto !important;
            /* sin vh */
            height: auto !important;
        }

        /* Oscurecer ligeramente la imagen */
        .hero-bg::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
        }



        /* ============================================
   4) HERO PANEL (caja blanca)
   ============================================ */
        .hero-panel {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 720px;

            background: rgba(255, 255, 255, 0.88);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }



        /* ============================================
   5) TEXTOS DEL HERO
   ============================================ */
        .hero-title {
            font-weight: 700;
            margin-bottom: .5rem;
            text-align: center;
            color: #0b3558;
        }

        .hero-sub {
            margin-bottom: 1rem;
            text-align: center;
            color: #345;
        }



        /* ============================================
   6) CAMPANITA
   ============================================ */
        .notif-btn {
            position: relative;
            border: 0;
            background: transparent;
            color: #fff;
            padding: .25rem .5rem;
        }

        .notif-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(35%, -35%);
        }



        /* ============================================
   7) FOOTER
   ============================================ */
        footer {
            flex-shrink: 0;
            background-color: #0b3558 !important;
            color: #fff;
            text-align: center;
            padding: 12px 0;
        }



        /* ============================================
   8) MOBILE FIX (iPhone / Android)
   ============================================ */
        @media (max-width: 576px) {
            .hero-panel {
                margin-top: 20px;
                padding: 1.75rem 1.25rem;
            }

            .hero-title {
                font-size: 1.6rem;
                line-height: 1.2;
            }

            .hero-sub {
                font-size: 1.05rem;
                line-height: 1.3;
            }
        }
        }



        /* ============================================
   9) DESKTOP & TABLETS GRANDES
   ============================================ */
        @media (min-width: 992px) {
            .hero-bg {
                background-attachment: scroll !important;
                /* evitar saltos en Safari */
            }
        }



        /* ============================================
   10) SAFARI / MAC FIX ESPECIAL
   ============================================ */
        @supports (-webkit-touch-callout: none) {
            .hero-bg {
                min-height: auto !important;
                height: auto !important;
                background-attachment: scroll !important;
            }
        }

        /* === FRANJA AZUL INFERIOR PERFECTAMENTE PROPORCIONADA === */

        /* Elimina azul gigante del body */
        body {
            background-color: #fff !important;
            /* fondo blanco real */
        }

        /* Crea una franja azul debajo del HERO */
        .hero-bottom-bar {
            background-color: #1877f2;
            height: 70px;
            /* idéntica al navbar */
            width: 100%;
        }

        /* === HERO FULL-SCREEN SIN FRANJAS BLANCAS === */
        .hero-bg {
            min-height: calc(100vh - 56px - 70px) !important;
            height: auto !important;
            background-image: url('img/fondo-buscotec.png');
            background-size: cover;
            background-position: center top;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-panel {
            width: 100%;
            text-align: left;
            /* Alineado a la izquierda para Desktop */
        }

        .arrow-animate-left {
            animation: arrowLeft 1.4s infinite ease-in-out;
        }

        .arrow-animate-right {
            animation: arrowRight 1.4s infinite ease-in-out;
        }

        @keyframes arrowLeft {
            0% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(-6px);
            }

            100% {
                transform: translateX(0);
            }
        }

        @keyframes arrowRight {
            0% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(6px);
            }

            100% {
                transform: translateX(0);
            }
        }
    </style>
    <style>
        /* Corrige la altura del carrusel */
        #introCarousel .carousel-inner {
            min-height: 520px;
            /* Ajustable si querés más alto */
        }

        #introCarousel .carousel-item {
            min-height: 520px;
            padding-bottom: 20px;
            /* evita cortes en móvil */
        }

        /* Permite que el contenido sea flexible sin romper */
        #introCarousel .carousel-item>* {
            flex-grow: 1;
        }

        .help-btn {
            z-index: 20;
            background: rgba(255, 255, 255, 0.85);
            padding: 6px 10px;
            border-radius: 12px;
            backdrop-filter: blur(4px);
            transition: transform 0.2s ease;
        }

        .help-btn:hover {
            transform: scale(1.1);
        }

        /* Asegura que las flechas estén por encima de todo */
        .carousel-control-prev,
        .carousel-control-next {
            z-index: 50 !important;
        }

        /* Mantener el botón de ayuda sin interferir con flechas */
        .help-btn {
            z-index: 20 !important;
            pointer-events: auto;
        }

        /* Evita que el contenedor del slide bloquee clics */
        .carousel-item {
            position: relative;
            z-index: 1;
        }

        /* 🔵 Capas de Modales y Flechas (Fix Pantalla Oscura) */
        .modal-backdrop {
            z-index: 2000 !important;
            opacity: 0.45 !important;
        }

        .modal {
            z-index: 3000 !important;
        }

        #introCarousel .carousel-control-prev,
        #introCarousel .carousel-control-next {
            z-index: 4000 !important;
            opacity: 1 !important;
        }

        /* 🔥 Botón de ayuda SIN tapar flechas */
        .help-btn {
            z-index: 3500 !important;
        }

        /* 🔥 Forzar flechas por encima del contenido del slide, logos, textos y botón help */
        #introCarousel .carousel-control-prev,
        #introCarousel .carousel-control-next {
            z-index: 9999 !important;
            /* más alto que todo */
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* 🔥 Fondo transparente real en las flechas (Safari fix) */
        #introCarousel .carousel-control-prev-icon,
        #introCarousel .carousel-control-next-icon {
            background-color: transparent !important;
            filter: drop-shadow(0 0 4px rgba(0, 0, 0, 0.3));
            /* para que se vean mejor */
        }

        /* 🔥 Asegurar que los SLIDES no tapen las flechas */
        .carousel-item {
            position: relative;
            z-index: 1 !important;
        }

        /* 🔥 Asegurar que help-btn NO tape las flechas */
        .help-btn {
            z-index: 5000 !important;
        }

        /* Ocultar flechas originales de Bootstrap */
        .carousel-control-prev,
        .carousel-control-next {
            display: none !important;
        }

        /* ===========================
   DISEÑO TIPO APP (NUEVO)
   =========================== */
        :root {
            --app-blue: #1877f2;
            --app-bg: #f8f9fa;
            --app-card-bg: #ffffff;
            --app-text: #333;
        }

        body.app-view {
            background-color: var(--app-bg) !important;
            padding-bottom: 180px;
            /* espacio para la nav inferior + footer */
        }

        .app-header {
            background-color: var(--app-blue);
            color: white;
            padding: 60px 20px 80px 20px;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            position: relative;
        }

        .app-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .search-container-app {
            margin-top: -40px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .search-box-app {
            background: white;
            border-radius: 20px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .search-box-app input {
            border: none;
            flex: 1;
            margin-left: 10px;
            font-size: 1rem;
            outline: none;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 30px 20px;
        }

        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            text-decoration: none;
            color: var(--app-text);
        }

        .category-icon-box {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .category-item:hover .category-icon-box {
            transform: scale(1.1);
        }

        .category-item span {
            font-size: 0.75rem;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .section-title {
            padding: 0 20px;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            height: 70px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.08);
            z-index: 1000;
            padding-bottom: env(safe-area-inset-bottom);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .nav-item-app {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #888;
            font-size: 0.7rem;
        }

        .nav-item-app.active {
            color: var(--app-blue);
        }

        .nav-item-app i {
            font-size: 1.4rem;
            margin-bottom: 2px;
        }

        /* Ocultar elementos si NO estamos en vista APP */
        .app-only {
            display: none;
        }

        body.is-mobile .app-only {
            display: block;
        }

        body.is-mobile .desktop-only {
            display: none;
        }

        @media (max-width: 768px) {
            .app-only {
                display: block !important;
            }

            .desktop-only {
                display: none !important;
            }

            body {
                background-color: var(--app-bg) !important;
            }
        }

        /* ===========================
       ESTILOS MAPA APP (FLOTANTE)
       =========================== */
        .map-wrapper-app {
            position: relative;
            height: 250px;
            width: 100%;
        }

        #mapContainerApp {
            height: 100%;
            width: 100%;
            z-index: 1 !important;
        }

        .prof-floating-card {
            position: absolute;
            bottom: 12px;
            left: 12px;
            right: 12px;
            background: #fff;
            border-radius: 12px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            z-index: 1000 !important;
            border: 1px solid #eee;
        }

        .prof-card-img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0f2f5;
        }

        .prof-card-info {
            flex: 1;
            min-width: 0;
        }

        .prof-card-name {
            font-weight: 700;
            color: #333;
            font-size: 0.95rem;
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .prof-card-cat {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0;
        }

        .prof-card-dist {
            font-size: 0.75rem;
            color: #999;
            margin-bottom: 0;
        }

        .badge-disponible {
            background: #4caf50;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        /* Admin link: solo visible en móvil (pantallas pequeñas) */
        @media (min-width: 769px) {
            .admin-link-only {
                display: none !important;
            }
        }
    </style>

    <script>
        // Redirección ultra-agresiva a WWW + Limpieza de Service Worker (Crítico para PWA/Caché)
        if (window.location.hostname === 'buscotec.com.ar') {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) { registration.unregister(); }
                    window.location.replace('https://www.buscotec.com.ar' + window.location.pathname + window.location.search);
                }).catch(function() {
                    window.location.replace('https://www.buscotec.com.ar' + window.location.pathname + window.location.search);
                });
            } else {
                window.location.replace('https://www.buscotec.com.ar' + window.location.pathname + window.location.search);
            }
        }
    </script>
    <script>
        (function (w, d, s, id) {
            if (typeof (w.webpushr) !== 'undefined') return;
            w.webpushr = w.webpushr || function () { (w.webpushr.q = w.webpushr.q || []).push(arguments) };
            var js, fjs = d.getElementsByTagName(s)[0];
            js = d.createElement(s); js.id = id; js.async = 1;
            js.src = "https://cdn.webpushr.com/app.min.js";
            fjs.parentNode.appendChild(js);
        }(window, document, 'script', 'webpushr-jssdk'));

        window.webpushr('setup', {
            'key': 'BDR63kCbkqbFxjISA6Bd8yiiZ4KXM4YBKsn9dkuMOMRjNnVWdDy0hghctod9ErIX0KEFpSwLsYhcMVJuFMtXEyc',
            'serviceWorker': '/sw.js',
            'integration': 'pwa'
        });
    </script>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">BuscoTec</a>

            <!-- 🔹 Bloque derecho: botones visibles siempre -->
            <div class="d-flex align-items-center ms-auto order-lg-2 gap-2">
                <!-- 🔔 Campanita (solo logueado) -->
                <button id="notifBell" class="notif-btn d-none" type="button" aria-label="Notificaciones">
                    <i class="bi bi-bell" style="font-size:1.35rem;"></i>
                    <span id="notifCount" class="badge bg-danger rounded-pill notif-badge d-none">0</span>
                </button>

                <!-- 👋 Saludo dinámico -->
                <span id="userGreeting" class="text-white fw-semibold d-none">
                    👋 Hola, <span id="userName"></span>
                </span>

                <!-- 🚪 Cerrar sesión (solo logueado) -->
                <button id="logoutBtnTop" class="btn btn-outline-light btn-sm d-none">Cerrar sesión</button>

                <!-- 🔐 Iniciar sesión / Registrarse (solo no logueado) -->
                <button id="loginBtnTop" class="btn btn-light btn-sm no-auth" data-bs-toggle="modal"
                    data-bs-target="#loginModal">
                    Iniciar sesión
                </button>
                <a href="seleccion_registro.html" id="registerBtnTop" class="btn btn-outline-light btn-sm no-auth">
                    Registrarse como usuario o profesional ACÁ
                </a>

                <!-- 🍔 Menú hamburguesa -->
                <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <!-- 🔽 Menú principal -->
            <div class="collapse navbar-collapse order-lg-1" id="navbarNav">
                <ul class="navbar-nav ms-auto" id="navList">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalQuienes">Quiénes
                            Somos</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="contactanos.html">Contactanos</a></li>
                    <li class="nav-item"><a class="nav-link" href="funciona.html">Cómo funciona la app</a></li>


                    <!-- Logueado -->
                    <li class="nav-item auth d-none">
                        <a class="nav-link" href="perfil_ver.html">
                            <i class="bi bi-person-circle me-1"></i> Ver perfil
                        </a>
                    </li>

                    <li class="nav-item auth d-none">
                        <a class="nav-link" href="perfil_editar.html">
                            <i class="bi bi-pencil-square me-1"></i> Editar perfil
                        </a>
                    </li>

                    <!-- 💰 Ver estado de cuenta -->
                    <li class="nav-item auth d-none" id="accountStatusLi">
                        <a class="nav-link" href="casos.html">
                            <i class="bi bi-wallet2 me-1"></i> Estado de cuenta
                        </a>
                    </li>

                    <li class="nav-item auth d-none admin-link-only">
                        <a class="nav-link" href="admin.html">Admin</a>
                    </li>

                    <!-- 🚪 Cerrar sesión dentro del menú (para móvil) -->
                    <li class="nav-item auth d-none">
                        <a class="nav-link" id="logoutBtn" href="#">
                            <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                        </a>
                    </li>


    </nav>


    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light border-0">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Iniciar sesión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
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
                                <button class="btn btn-outline-secondary" type="button" id="toggleLoginPass" onclick="
                  var pass = document.getElementById('loginPass');
                  var btn = document.getElementById('toggleLoginPass');
                  var icon = btn.querySelector('i');
                  if (pass.type === 'password') {
                    pass.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                  } else {
                    pass.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                  }
                ">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-success w-100" type="submit">Entrar</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="olvido.html" class="text-info">¿Olvidaste tu contraseña?</a>
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
                    <a href="mensajes.html" class="btn btn-primary">Ver mensajes</a>
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

    <main>
        <!-- 📱 VISTA APP (Móvil) -->
        <div class="app-only">
            <div class="app-header">
                <h1 id="appGreeting">¡Hola! ¿Qué servicio necesitás hoy?</h1>
            </div>

            <div class="search-container-app">
                <div class="search-box-app">
                    <i class="bi bi-search text-secondary"></i>
                    <input type="text" id="appSearchInput" placeholder="Buscar servicios..." autocomplete="off">
                </div>
                <!-- Sugerencias app -->
                <ul id="appSugerencias" class="list-group shadow-lg mt-2 rounded d-none"
                    style="position: absolute; width: calc(100% - 40px); z-index: 1050; max-height: 250px; overflow-y: auto;">
                </ul>
            </div>

            <div class="categories-grid" id="appCategoriesGrid">
                <!-- Se llena por JS -->
            </div>

            <div class="section-title">Profesionales cerca tuyo</div>
            <div class="px-3">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="map-wrapper-app">
                        <div id="mapContainerApp"></div>

                        <!-- Tarjeta Flotante (Se llena dinámicamente) -->
                        <div id="profFloatingCard" class="prof-floating-card d-none">
                            <img src="/img/placeholder.png" alt="" class="prof-card-img" id="profCardFoto">
                            <div class="prof-card-info">
                                <p class="prof-card-name" id="profCardNombre"></p>
                                <p class="prof-card-cat" id="profCardCat"></p>
                                <p class="prof-card-dist"><i class="bi bi-geo-alt-fill"></i> <span
                                        id="profCardDist"></span></p>
                            </div>
                            <div class="badge-disponible">Disponible</div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <p class="small text-muted mb-0">Hacia dónde vamos: toca un punto azul para ver detalles.</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- 💻 VISTA DESKTOP (Actualizada para encajar todo en una sola pantalla) -->
        <section class="hero-bg desktop-only"
            style="background-image: url('img/fondo-buscotec.png'); background-size: cover; background-position: center; height: 70vh; padding: 0; position: relative;">
            <style>
                .hero-bg.desktop-only {
                    display: flex;
                    align-items: center;
                }

                @media (max-width: 768px) {
                    .hero-bg.desktop-only {
                        display: none !important;
                    }
                }
            </style>

            <!-- BOTONES ABAJO A LA DERECHA -->
            <div class="d-flex align-items-center gap-2"
                style="position: absolute; bottom: 30px; right: 50px; z-index: 10;">
                <div class="text-center">
                    <div class="d-flex align-items-center bg-dark text-white rounded-3 px-2 py-1 opacity-50"
                        style="cursor: not-allowed; border: 1px solid #444;">
                        <i class="bi bi-apple fs-5 me-1"></i>
                        <div class="lh-1 text-start">
                            <span style="font-size: 7px; display: block; color: #aaa;">App Store</span>
                            <span class="fw-bold" style="font-size: 10px;">PROXIMAMENTE</span>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <div class="d-flex align-items-center bg-dark text-white rounded-3 px-2 py-1 opacity-50"
                        style="cursor: not-allowed; border: 1px solid #444;">
                        <i class="bi bi-google-play fs-5 me-1"></i>
                        <div class="lh-1 text-start">
                            <span style="font-size: 7px; display: block; color: #aaa;">Google Play</span>
                            <span class="fw-bold" style="font-size: 10px;">PROXIMAMENTE</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container-fluid px-5">
                <div class="row align-items-center">
                    <!-- TODO EL CONTENIDO A LA IZQUIERDA -->
                    <div class="col-lg-6 col-xl-5">

                        <h1 class="fw-bold mb-2 text-start"
                            style="font-size: 3rem; color: #0b3558; line-height: 1.1; font-family: 'Inter', sans-serif;">
                            ¿Estas Buscando profesionales disponibles en tu zona? <br>
                        </h1>
                        <p class="mb-3 fs-5 text-start"
                            style="max-width: 500px; line-height: 1.2; color: #0d3b66; font-weight: 600; text-shadow: 1px 1px 2px rgba(255,255,255,0.8);">

                        </p>

                        <div id="alerta" class="alert text-center d-none mb-2"></div>

                        <!-- ⬜ Tarjeta de Búsqueda Compacta -->
                        <div class="card border-0 shadow-lg p-3 mb-3"
                            style="background: rgba(255, 255, 255, 0.75); border-radius: 20px; backdrop-filter: blur(10px);">
                            <h6 class="fw-bold mb-2 d-flex align-items-center" style="color: #0b3558;">
                                <i class="bi bi-search me-2 text-primary"></i>Escribí lo que buscas o selecciona una
                                categoría.
                            </h6>

                            <div class="mb-2 position-relative">
                                <div class="d-flex align-items-center bg-white rounded-pill p-1 border shadow-sm"
                                    style="height: 50px;">
                                    <i class="bi bi-search ms-3 text-muted"></i>
                                    <input type="text" id="buscadorCategoria"
                                        class="form-control border-0 bg-transparent ps-2"
                                        placeholder="Ejemplo: plomero, electricista..."
                                        style="box-shadow: none; font-size: 0.9rem;" autocomplete="off">
                                    <button class="btn btn-primary rounded-pill px-3 fw-bold me-1"
                                        style="height: 38px; background-color: #3b82f6; border: none; font-size: 0.85rem;">
                                        Buscar
                                    </button>
                                </div>
                                <ul id="listaSugerencias"
                                    class="list-group position-absolute w-100 shadow-lg mt-1 rounded d-none overflow-hidden"
                                    style="z-index: 1050; max-height: 150px;"></ul>
                            </div>

                            <div class="bg-white rounded-pill px-3 border shadow-sm d-flex align-items-center"
                                style="height: 45px;">
                                <select id="categoriaSelect"
                                    class="form-select border-0 bg-transparent text-secondary cursor-pointer"
                                    style="box-shadow: none; font-size: 0.9rem;">
                                    <option value="">Selecciona una categoría</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha Vacía -->
                    <div class="col-lg-6 col-xl-7"></div>
                </div>
            </div>
        </section>
    </main>

    <!-- 🧭 NAV INFERIOR (Solo vista APP) -->
    <div class="bottom-nav app-only">
        <a href="index.php" class="nav-item-app active">
            <i class="bi bi-house-door-fill"></i>
            <span>Inicio</span>
        </a>
        <a href="#" class="nav-item-app" onclick="document.getElementById('appSearchInput').focus(); return false;">
            <i class="bi bi-grid-fill"></i>
            <span>Categorías</span>
        </a>
        <a href="mensajes.html" class="nav-item-app">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Mensajes</span>
        </a>
        <a href="perfil_ver.html" class="nav-item-app nav-auth-only d-none">
            <i class="bi bi-person-fill"></i>
            <span>Perfil</span>
        </a>
        <a href="#" class="nav-item-app nav-no-auth-only" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-person-fill"></i>
            <span>Ingresar</span>
        </a>
    </div>


    <!-- 🔹 Modal Quiénes Somos -->
    <div class="modal fade" id="modalQuienes" tabindex="-1" aria-labelledby="modalQuienesLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg">

                <!-- Encabezado -->
                <div class="modal-header bg-primary text-white rounded-top-4">
                    <h5 class="modal-title" id="modalQuienesLabel">
                        <i class="bi bi-person-lines-fill me-2"></i>Equipo Fundador de BuscoTec
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                <!-- Cuerpo -->
                <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto; background-color: #f8f9fa;">
                    <!-- Pablo -->
                    <div class="mb-4 border-start border-4 border-primary ps-3">
                        <h5 class="fw-bold text-primary mb-1">¿Quiénes somos?</h5>
                        <p class="mb-0">
                            Somos una empresa creada en 2025 con el objetivo de conectar clientes con profesionales
                            independientes de manera simple, segura y eficiente.

                            Detectamos una problemática muy común: cuando surge una necesidad cotidiana —como encontrar
                            un plomero, gasista, electricista u otro profesional— las personas suelen recurrir a
                            recomendaciones informales. Muchas veces no reciben respuesta o terminan contratando sin
                            conocer la experiencia, referencias ni la forma de trabajo del profesional.

                            Por eso desarrollamos una plataforma que permite a los usuarios:

                            Conocer previamente al profesional que van a contratar

                            Acceder a valoraciones y opiniones de otros clientes

                            Contar con datos validados, como verificación de identidad mediante DNI

                            Tener mayor seguridad y respaldo ante cualquier inconveniente

                            Al mismo tiempo, el profesional mantiene el control total: decide si aceptar o no cada
                            solicitud de trabajo, conservando su autonomía.

                            Nuestro objetivo principal es no interferir en las ganancias del profesional. No cobramos
                            porcentajes ni cargos fijos. Solo se abona un cargo mínimo cuando el profesional consigue un
                            cliente a través de la plataforma.


                        </p>
                    </div>

                    <!-- Oscar -->
                    <div class="border-start border-4 border-primary ps-3">
                        <h5 class="fw-bold text-primary mb-1">Nuestra Misión</h5>
                        <p class="mb-0">
                            Conectar profesionales independientes con clientes de forma simple, segura y eficiente,
                            mediante un modelo de negocio justo y accesible.

                            Si no conseguís clientes, no pagás.
                            No hay abonos ni costos fijos.
                        </p>
                    </div>
                    <!-- Oscar -->
                    <div class="border-start border-4 border-primary ps-3">
                        <h5 class="fw-bold text-primary mb-1">Nuestra Visión</h5>
                        <p class="mb-0">
                            Convertirnos en la plataforma digital líder de profesionales independientes en Argentina y
                            expandirnos a Latinoamérica, siendo sinónimo de confianza, transparencia y oportunidades
                            reales de trabajo.

                        </p>
                    </div>

                </div>

                <!-- Pie -->
                <div class="modal-footer border-0 flex-column text-center" style="background:#eef3fb;">
                    <a href="https://buscotec.com.ar/seleccion_registro.html" target="_blank"
                        class="btn w-100 fw-semibold shadow-sm text-white"
                        style="background: linear-gradient(135deg,#1877F2,#0d47a1); border:none;">
                        🚀 Registrate ahora, es GRATIS
                    </a>

                    <button type="button" class="btn btn-outline-primary w-100 fw-semibold mt-2"
                        data-bs-dismiss="modal">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="hero-bottom-bar"></div>


    <footer class="bg-dark text-light pt-4 pb-3" style="margin-bottom: 0;">
        <style>
            @media (max-width: 768px) {
                footer {
                    margin-bottom: 0 !important;
                    padding-bottom: 150px !important; /* Aumentado de 120px a 150px para dar más aire */
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
                    await fetch('backend/sesion_estable.php', {
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
        function canSeeAdmin(email) {
            return !!email && ADMIN_ALLOWLIST.has(String(email).trim().toLowerCase());
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

        // Actualiza el menú según el estado de sesión
        function updateNavUI(isLogged, nombre = 'Usuario', pending = 0, email = null) {
            toggleAll('.auth', isLogged);
            toggleAll('.no-auth', !isLogged);
            toggleAll('.nav-auth-only', isLogged);
            toggleAll('.nav-no-auth-only', !isLogged);
            document.getElementById('notifBell')?.classList.toggle('d-none', !isLogged);

            const userMenu = document.getElementById('userMenu');
            if (userMenu) userMenu.textContent = `Hola, ${nombre}`;

            const appGreeting = document.getElementById('appGreeting');
            if (appGreeting) appGreeting.textContent = isLogged ? `¡Hola ${nombre}! ¿Qué necesitás?` : '¡Hola! ¿Qué servicio necesitás hoy?';

            const notif = document.getElementById('notifCount');
            if (notif) {
                notif.textContent = pending;
                notif.classList.toggle('d-none', pending <= 0);
            }

            // Mostrar Admin solo si el email está autorizado (CSS oculta en desktop)
            toggleAll('.admin-link-only', canSeeAdmin(email));

            // 🛡️ Solo para profesionales: Estado de cuenta
            const role = localStorage.getItem('buscotec_role');
            const accountStatusLi = document.getElementById('accountStatusLi');
            if (accountStatusLi) {
                accountStatusLi.classList.toggle('d-none', !isLogged || role !== 'profesional');
            }
        }


        // Envía ubicación al backend
        function postGeolocation(userId, role) {
            if (!('geolocation' in navigator) || !userId) return;
            navigator.geolocation.getCurrentPosition((pos) => {
                const { latitude, longitude } = pos.coords || {};
                if (latitude && longitude) {
                    fetch('backend/registrar_ubicacion.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&lat=${encodeURIComponent(latitude)}&lng=${encodeURIComponent(longitude)}`
                    }).catch(() => { });
                }
            }, () => { }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        }

        // Redirección según rol
        function goAfterRole(role) {
            const routes = { usuario: null, profesional: null };
            const target = routes[role];
            if (target) window.location.href = target;
        }



        // --- Lógica de Modales y Roles ---

        function showRoleChooser(payload) {
            forceModalCleanup(); // Limpiar antes de mostrar el nuevo modal
            const roleModalEl = document.getElementById('roleModal');
            if (!roleModalEl) return;
            const roleModal = bootstrap.Modal.getOrCreateInstance(roleModalEl);

            const btnUsuario = document.getElementById('btnRolUsuario');
            const btnProf = document.getElementById('btnRolProfesional');

            btnUsuario.onclick = () => {
                const userId = payload.roles?.usuario || payload.userIdFallback;
                saveSession('usuario', userId, payload.nombre, payload.email);
                updateNavUI(true, payload.nombre, payload.pending || 0, payload.email);
                postGeolocation(userId, 'usuario');
                roleModal.hide();
                setTimeout(forceModalCleanup, 400); // Limpieza después de cerrar
                goAfterRole('usuario');
                initPushAfterLogin();
            };

            btnProf.onclick = () => {
                const userId = payload.roles?.profesional || payload.userIdFallback;
                saveSession('profesional', userId, payload.nombre, payload.email);
                updateNavUI(true, payload.nombre, payload.pending || 0, payload.email);
                postGeolocation(userId, 'profesional');
                roleModal.hide();
                setTimeout(forceModalCleanup, 400); // Limpieza después de cerrar
                goAfterRole('profesional');
                initPushAfterLogin();
            };

            roleModal.show();
        }


        async function initPushAfterLogin() {
            const userId = localStorage.getItem("buscotec_user_id");
            const role = localStorage.getItem("buscotec_role");

            if (!userId || !role) {
                console.warn("[push] ⚠️ No hay sesión activa, se omite actualización de Webpushr.");
                return;
            }

            // Espera a que el SDK esté listo
            const waitForSDK = () => new Promise((resolve, reject) => {
                let tries = 0;
                const t = setInterval(() => {
                    tries++;
                    if (typeof window.webpushr === "function") {
                        clearInterval(t); resolve();
                    }
                    if (tries > 50) { // ~100s máx
                        clearInterval(t); reject(new Error("Webpushr SDK no cargó"));
                    }
                }, 2000);
            });

            // Obtiene el SID con fallback
            const getSubscriberId = () => new Promise((resolve) => {
                try {
                    if (typeof webpushr !== 'function') {
                        console.warn('[push] webpushr global no es funcion');
                        return resolve("");
                    }

                    webpushr('fetch_id', function (sid) {
                        console.log("[push] fetch_id resultado:", sid);
                        if (sid && sid !== "0") {
                            resolve(String(sid));
                        } else {
                            webpushr('get', 'subscriber_id', function (sid2) {
                                console.log("[push] get subscriber_id resultado:", sid2);
                                resolve(sid2 ? String(sid2) : "");
                            });
                        }
                    });
                } catch (e) {
                    console.warn("[push] Error excepcion", e);
                    resolve("");
                }
            });

            try {
                await waitForSDK();
                let sid = "";
                for (let i = 0; i < 8; i++) {
                    sid = await getSubscriberId();
                    if (sid) break;
                    await new Promise(r => setTimeout(r, 2000));
                }

                if (!sid) {
                    console.warn("[push] ❌ No se pudo obtener subscriberId de Webpushr");
                    return;
                }

                const lastSid = localStorage.getItem("webpushr_sid") || "";
                if (sid !== lastSid) {
                    console.log("[push] ✅ Nuevo subscriberId:", sid);
                    localStorage.setItem("webpushr_sid", sid);
                }

                const payload = {
                    user_id: parseInt(userId, 10),
                    rol: role,
                    webpushr_id: sid
                };

                const res = await fetch("/backend/guardar_suscripcion.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "include",
                    body: JSON.stringify(payload)
                });

                const txt = await res.text();
                let j = {};
                try { j = JSON.parse(txt); } catch { j = { ok: false, raw: txt }; }

                if (j.ok) {
                    console.log("[push] 🆗 BBDD actualizada:", j);
                } else {
                    console.warn("[push] ⚠️ Respuesta backend:", j);
                }
            } catch (err) {
                console.error("[push] ❌ Error general:", err);
            }
        }



        // --- Autenticación y Carga Inicial ---

        async function initAuthUI() {
            const saved = getSavedSession();

            // 1. UI Optimista (Mostrar sesión guardada mientras carga)
            if (saved.userId) {
                updateNavUI(true, saved.nombre, 0, saved.email);
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

                updateNavUI(true, nombre, pending, email);
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

            }
        }

        // 🧹 LIMPIEZA AGRESIVA DE MODALES (Evita pantalla oscura)
        function forceModalCleanup() {
            console.log("🧹 [Modales] Ejecutando limpieza profunda...");
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            // Eliminar todos los backdrops que Bootstrap a veces olvida
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }



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
                fetch('backend/get_estado_servicio.php', { credentials: 'include' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok && typeof data.estado === 'boolean') {
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
                            actualizarBotonEstado(data.nuevo_estado);
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
                const res = await fetch("backend/get_unread_count.php", { credentials: "include" });
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
                        const res = await fetch("backend/get_unread_count.php", { credentials: "include" });
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
                    const res = await fetch('backend/login.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `correo=${encodeURIComponent(email)}&clave=${encodeURIComponent(password)}`
                    });

                    const status = res.status;
                    const raw = await res.text();
                    let data = null;
                    try { 
                        data = JSON.parse(raw); 
                    } catch (_) { 
                        console.error("No JSON", raw); 
                        msg.className = 'alert alert-danger';
                        msg.textContent = `Error del servidor (${status}): Respuesta no válida.`;
                        return;
                    }

                    if (!data?.success) {
                        msg.className = 'alert alert-danger';
                        msg.textContent = data?.message || `Error al iniciar sesión (${status})`;
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
                        const loginModal = bootstrap.Modal.getInstance(loginModalEl) || new bootstrap.Modal(loginModalEl);
                        loginModal.hide();
                        setTimeout(forceModalCleanup, 400);
                    }

                    // Manejo de roles
                    if (roles.includes('usuario') && roles.includes('profesional')) {
                        // Esperar un poco a que limpie el anterior antes de mostrar el selector de roles
                        setTimeout(() => {
                            showRoleChooser({ roles: data.role_ids, nombre, pending, email: emailUser, userIdFallback });
                        }, 500);
                    } else {
                        const selectedRole = roles[0] || (data.role || 'usuario');
                        const roleIds = data.role_ids || {};
                        const roleId = roleIds[selectedRole] || roleIds['profesional'] || roleIds['usuario'] || data.id || userIdFallback;

                        saveSession(selectedRole, roleId, nombre, emailUser);
                        updateNavUI(true, nombre, pending, emailUser);
                        updateTopButtons(true, nombre);
                        postGeolocation(roleId, selectedRole);
                        initPushAfterLogin();
                        goAfterRole(selectedRole);

                        setTimeout(forceModalCleanup, 600);
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
                    window.location.href = "/index.php";

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
            const grid = document.getElementById("appCategoriesGrid");
            if (!select) return;

            select.innerHTML = "<option value=''>Cargando categorías...</option>";

            fetch("backend/get_categorias.php", { cache: "no-store" })
                .then(res => {
                    if (!res.ok) throw new Error("Error HTTP " + res.status);
                    return res.json();
                })
                .then(data => {
                    const categorias = Array.isArray(data.items) ? data.items : [];

                    if (categorias.length === 0) {
                        select.innerHTML = "<option value=''>No hay categorías disponibles</option>";
                        return;
                    }

                    select.innerHTML = "<option value=''>Selecciona una categoría</option>";
                    if (grid) grid.innerHTML = "";

                    // 1. Select Desktop: Mostrar TODAS
                    categorias.forEach(cat => {
                        const opt = document.createElement("option");
                        opt.value = cat.id;
                        opt.textContent = cat.nombre;
                        select.appendChild(opt);
                    });

                    // 2. Grilla App (Móvil): SOLO 8 PRIORITARIAS
                    if (grid) {
                        // Lista exacta de 8 claves
                        const PRIORITY_KEYS = ['plomero', 'electricista', 'gasista', 'albañil', 'carpintero', 'cerrajero', 'pintor', 'flete'];

                        const iconMap = {
                            'electricista': 'bi-lightning-fill',
                            'plomero': 'bi-droplet-fill',
                            'cerrajero': 'bi-key-fill',
                            'carpintero': 'bi-hammer',
                            'gasista': 'bi-fire',
                            'albañil': 'bi-bricks',
                            'pintor': 'bi-paint-bucket',
                            'flete': 'bi-truck'
                        };

                        const colorMap = {
                            'electricista': '#fff9c4',
                            'plomero': '#e1f5fe',
                            'cerrajero': '#f3e5f5',
                            'carpintero': '#efebe9',
                            'gasista': '#fbe9e7',
                            'albañil': '#e0f2f1',
                            'pintor': '#fce4ec',
                            'flete': '#e8eaf6'
                        };

                        // Filtrar y ordenar según PRIORITY_KEYS
                        PRIORITY_KEYS.forEach(key => {
                            const cat = categorias.find(c => c.nombre.toLowerCase().includes(key));
                            if (cat) {
                                const iconClass = iconMap[key] || 'bi-gear-fill';
                                const bgColor = colorMap[key] || '#f8f9fa';

                                const item = document.createElement("a");
                                item.href = `categoria.php?id=${cat.id}&nombre=${encodeURIComponent(cat.nombre)}`;
                                item.className = "category-item";
                                item.innerHTML = `
                <div class="category-icon-box" style="background-color: ${bgColor};">
                  <i class="bi ${iconClass}" style="color: #333;"></i>
                </div>
                <span>${cat.nombre}</span>
              `;
                                grid.appendChild(item);
                            }
                        });
                    }

                    // 3. Inicializar buscadores (Desktop y App)
                    initUnifiedSearch(categorias, "buscadorCategoria", "listaSugerencias");
                    initUnifiedSearch(categorias, "appSearchInput", "appSugerencias");
                })
                .catch(err => {
                    console.error("Error al cargar categorías:", err);
                    select.innerHTML = "<option value=''>Error al cargar categorías</option>";
                });

            // Redirección al seleccionar en el <select>
            select.addEventListener("change", () => {
                const categoriaId = select.value;
                if (categoriaId) {
                    const categoriaNombre = select.options[select.selectedIndex].textContent;
                    window.location.href = `categoria.php?id=${categoriaId}&nombre=${encodeURIComponent(categoriaNombre)}`;
                }
            });
        }

        function initUnifiedSearch(categorias, inputId, listId) {
            const input = document.getElementById(inputId);
            const lista = document.getElementById(listId);
            if (!input || !lista) return;

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
                        li.onclick = () => {
                            window.location.href = `categoria.php?id=${cat.id}&nombre=${encodeURIComponent(cat.nombre)}`;
                        };
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

            // Ubicación por defecto (Bariloche centro)
            let userPos = { lat: -41.13437, lng: -71.30822 };

            // Intentar obtener ubicación real
            try {
                const pos = await new Promise((res, rej) => {
                    navigator.geolocation.getCurrentPosition(res, rej, { timeout: 5000 });
                });
                userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            } catch (e) {
                console.warn("No se pudo obtener ubicación para el mapa app, usando por defecto.");
            }

            appMap = L.map('mapContainerApp', { zoomControl: false }).setView([userPos.lat, userPos.lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(appMap);

            // Marcador de usuario (punto azul sutil)
            L.circleMarker([userPos.lat, userPos.lng], {
                radius: 8, fillOpacity: 0.9, color: 'white', weight: 2, fillColor: '#1877f2'
            }).addTo(appMap);

            // Cargar profesionales cercanos
            loadNearbyProfessionals(userPos.lat, userPos.lng);
        }

        async function loadNearbyProfessionals(lat, lng) {
            try {
                const res = await fetch(`backend/get_profesionales_cercanos.php?lat=${lat}&lng=${lng}`);
                const data = await res.json();
                if (!data.ok) return;

                const customIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: "<div style='background-color:#1877f2; width:12px; height:12px; border-radius:50%; border:2px solid white; box-shadow:0 0 5px rgba(0,0,0,0.3);'></div>",
                    iconSize: [12, 12],
                    iconAnchor: [6, 6]
                });

                data.profesionales.forEach(p => {
                    const marker = L.marker([p.lat, p.lng], { icon: customIcon }).addTo(appMap);
                    marker.on('click', () => {
                        showProfFloatingCard(p);
                    });
                    appMarkers.push(marker);
                });

                // Mostrar el primero por defecto si hay
                if (data.profesionales.length > 0) {
                    showProfFloatingCard(data.profesionales[0]);
                }

            } catch (e) {
                console.error("Error cargando profesionales para el mapa app", e);
            }
        }

        function showProfFloatingCard(p) {
            const card = document.getElementById('profFloatingCard');
            const nombre = document.getElementById('profCardNombre');
            const cat = document.getElementById('profCardCat');
            const dist = document.getElementById('profCardDist');
            const foto = document.getElementById('profCardFoto');
            const badge = card?.querySelector('.badge-disponible');

            if (!card || !nombre || !cat || !dist || !foto) return;

            nombre.textContent = p.nombre;
            cat.textContent = p.categoria;
            dist.textContent = p.distancia + " m";
            foto.src = p.foto;

            if (badge) {
                if (p.disponible) {
                    badge.textContent = "Disponible";
                    badge.style.backgroundColor = "#4caf50";
                } else {
                    badge.textContent = "Fuera de servicio";
                    badge.style.backgroundColor = "#999";
                }
            }

            card.classList.remove('d-none');

            // Centrar mapa un poco por encima para que la tarjeta no tape el marcador
            appMap.panTo([p.lat + 0.002, p.lng], { animate: true });

            // Hacer que la tarjeta redirija al perfil profesional al tocarla (excepto el botón)
            card.onclick = (e) => {
                if (e.target.tagName !== 'BUTTON' && !e.target.classList.contains('badge-disponible')) {
                    window.location.href = `perfil_profesional.html?id=${p.id}`;
                }
            };
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
            initEstadoServicio();
            initNotifBell();
            initCategorias();
            checkUrlMessages();

            // Inicializar Mapa App si estamos en móvil
            if (document.body.classList.contains('app-view')) {
                initAppMap();
            }



            // 🧹 Limpieza de seguridad para "pantalla oscura" (backdrops residuales)
            const cleanupModals = () => {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            };

            // Ejecutar limpieza al inicio y en eventos clave
            cleanupModals();
            window.addEventListener('load', cleanupModals);
            document.addEventListener('hide.bs.modal', () => setTimeout(cleanupModals, 300));

            // PWA Service Worker
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
        fetch('backend/get_perfil.php', { credentials: 'include' })
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
                const r = await fetch('backend/session_from_jwt.php', { credentials: 'include', headers });
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
                const r = await fetch('backend/get_unread_count.php', { credentials: 'include' });
                const j = await r.json();
                const bell = document.getElementById('notifBell');
                const notif = document.getElementById('notifCount');

                if (j.ok) {
                    const total = parseInt(j.no_leidos ?? 0);
                    // 🔽 Mostrar campanita solo si hay mensajes no leídos
                    if (total > 0) {
                        notif.textContent = total;
                        notif.classList.remove('d-none');
                        bell.classList.remove('d-none');
                    } else {
                        notif.classList.add('d-none');
                        // bell.classList.remove('d-none'); // La campana siempre visible o según lógica de negocio
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

    <?php if (!isset($_SESSION['usuario_id'])): ?>
        <!-- ===================================================== -->
        <!-- MODAL DE BIENVENIDA / ONBOARDING - BUSCOTEC 2025 -->
        <!-- ===================================================== -->
        <div class="modal fade" id="testOnceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">

                    <!-- CABECERA -->
                    <div class="modal-header border-0 text-white text-center position-relative"
                        style="background: linear-gradient(135deg, #1877F2, #0d47a1);">
                        <h5 class="modal-title fw-bold w-100"> Bienvenido a BuscoTec</h5>
                        <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <!-- CUERPO -->
                    <div class="modal-body p-0" style="background: #f8faff;">
                        <div id="introCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-touch="true"
                            data-bs-interval="6000">

                            <!-- PUNTITOS -->
                            <div class="carousel-indicators mb-0">
                                <button type="button" data-bs-target="#introCarousel" data-bs-slide-to="0"
                                    class="active"></button>
                                <button type="button" data-bs-target="#introCarousel" data-bs-slide-to="1"></button>
                                <button type="button" data-bs-target="#introCarousel" data-bs-slide-to="2"></button>
                                <button type="button" data-bs-target="#introCarousel" data-bs-slide-to="3"></button>
                                <button type="button" data-bs-target="#introCarousel" data-bs-slide-to="4"></button>
                            </div>

                            <!-- SLIDES -->
                            <div class="carousel-inner">

                                <!-- FLECHAS CUSTOM SIEMPRE VISIBLES -->
                                <button id="slidePrev" type="button"
                                    class="btn btn-light shadow position-absolute top-50 start-0 translate-middle-y"
                                    data-bs-target="#introCarousel" data-bs-slide="prev"
                                    style="z-index:99999; border-radius:50%; width:48px; height:48px;">
                                    <i class="bi bi-chevron-left fs-4 text-primary"></i>
                                </button>

                                <button id="slideNext" type="button"
                                    class="btn btn-light shadow position-absolute top-50 end-0 translate-middle-y"
                                    data-bs-target="#introCarousel" data-bs-slide="next"
                                    style="z-index:99999; border-radius:50%; width:48px; height:48px;">
                                    <i class="bi bi-chevron-right fs-4 text-primary"></i>
                                </button>


                                <!-- ================================= -->
                                <!-- SLIDE 1 – BIENVENIDO            -->
                                <!-- ================================= -->
                                <div class="carousel-item active text-center p-4 position-relative">
                                    <a href="funciona.html" class="help-btn position-absolute top-0 end-0 m-3">
                                        <i class="bi bi-question-circle-fill fs-4 text-primary"></i>
                                    </a>

                                    <h2 class="fw-bold text-primary mb-3 text-uppercase">Bienvenido</h2>

                                    <p class="text-muted mb-4 fs-5" style="line-height:1.4;">
                                        BuscoTec conecta a usuarios con profesionales independientes
                                        de forma simple, rápida y segura.
                                        <br><br>
                                        Une a quienes necesitan un servicio con quienes saben hacerlo.
                                    </p>

                                    <img src="img/icons/icon-192.png"
                                        style="width:110px; opacity:0.95; margin-bottom:20px;">

                                    <div class="mt-3">
                                        <a href="seleccion_registro.html" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                            Crear cuenta en BuscoTec
                                        </a>
                                    </div>
                                </div>


                                <!-- ================================= -->
                                <!-- SLIDE 2 – COMO FUNCIONA         -->
                                <!-- ================================= -->
                                <div class="carousel-item text-center p-4 position-relative">
                                    <a href="funciona.html" class="help-btn position-absolute top-0 end-0 m-3">
                                        <i class="bi bi-question-circle-fill fs-4 text-primary"></i>
                                    </a>

                                    <h2 class="fw-bold text-primary mb-3 text-uppercase">Como funciona</h2>

                                    <div class="text-muted mb-4 fs-5" style="line-height:1.6;">
                                        <p class="mb-3">1. Buscás en el mapa.</p>
                                        <p class="mb-3">2. Enviás un mensaje.</p>
                                        <p class="mb-3">3. Hacés "match".</p>
                                        <p class="fw-bold text-primary">¡Y listo!</p>
                                    </div>

                                    <img src="img/icons/icon-192.png"
                                        style="width:110px; opacity:0.95; margin-bottom:10px; filter:drop-shadow(0 0 2px #ccc);">
                                </div>


                                <!-- ================================= -->
                                <!-- SLIDE 3 – PORQUE USARLO         -->
                                <!-- ================================= -->
                                <div class="carousel-item text-center p-4 position-relative">
                                    <a href="funciona.html" class="help-btn position-absolute top-0 end-0 m-3">
                                        <i class="bi bi-question-circle-fill fs-4 text-primary"></i>
                                    </a>

                                    <h2 class="fw-bold text-primary mb-3 text-uppercase">Porque usarlo</h2>

                                    <img src="img/icons/icon-192.png"
                                        style="width:110px; margin-bottom:15px; filter:drop-shadow(0 0 2px #ccc);">

                                    <div class="text-start mx-auto fs-5" style="max-width: 450px;">
                                        <p class="mb-2"><strong>Búsqueda fácil:</strong> encontrá profesionales cerca tuyo
                                            en segundos.</p>
                                        <p class="mb-2"><strong>Seguridad:</strong> profesionales con datos verificados.</p>
                                        <p class="mb-2"><strong>Más oportunidades:</strong> trabajos extra cuando quieras.
                                        </p>
                                        <p class="mb-2"><strong>Ganancia completa:</strong> solo pagás por contacto
                                            aceptado.</p>
                                        <p class="mb-0"><strong>Sin abono mensual:</strong> si no conseguís clientes, no
                                            pagás.</p>
                                    </div>
                                </div>


                                <!-- ================================= -->
                                <!-- SLIDE 4 – USUARIOS              -->
                                <!-- ================================= -->
                                <div class="carousel-item text-center p-4 position-relative">
                                    <a href="funciona.html" class="help-btn position-absolute top-0 end-0 m-3">
                                        <i class="bi bi-question-circle-fill fs-4 text-primary"></i>
                                    </a>

                                    <h2 class="fw-bold text-primary mb-3 text-uppercase">Usuarios</h2>

                                    <h5 class="fw-bold mb-3">Registrate como Usuario totalmente GRATIS</h5>

                                    <div class="text-start mx-auto fs-5" style="max-width: 480px;">
                                        <p class="mb-2">Buscá el profesional que necesitás.</p>
                                        <p class="mb-2">Elegilo en el mapa según tu zona.</p>
                                        <p class="mb-2">Enviá un mensaje con fotos si querés.</p>
                                        <p class="mb-2">Si acepta, te enviamos su contacto.</p>
                                        <p class="mb-2">Calificá su trabajo y listo.</p>
                                        <p class="mb-0 fw-bold text-success">Nunca se te cobrara un cargo por el servicio
                                        </p>
                                    </div>
                                </div>


                                <!-- ================================= -->
                                <!-- SLIDE 5 – PROFESIONALES         -->
                                <!-- ================================= -->
                                <div class="carousel-item text-center p-4 position-relative">
                                    <a href="funciona.html" class="help-btn position-absolute top-0 end-0 m-3">
                                        <i class="bi bi-question-circle-fill fs-4 text-primary"></i>
                                    </a>

                                    <h2 class="fw-bold text-primary mb-3 text-uppercase">Profesionales</h2>

                                    <h5 class="fw-bold mb-3">Registrate como PROFESIONAL totalmente GRATIS</h5>

                                    <div class="text-start mx-auto fs-5" style="max-width: 480px;">
                                        <p class="mb-2">Baja la app, Registrate GRATIS y elegí tus categorías.</p>
                                        <p class="mb-2">Agregá tu descripción y horarios.</p>
                                        <p class="mb-2">Recibí mensajes de usuarios.</p>
                                        <p class="mb-2">Solo pagás si aceptás el contacto.</p>
                                        <p class="mb-0">Hacés el trabajo, te pagan y sumás estrellas.</p>
                                    </div>
                                </div>

                            </div>

                            <!-- Controles flechas -->
                            <button class="carousel-control-prev" type="button" data-bs-target="#introCarousel"
                                data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>

                            <button class="carousel-control-next" type="button" data-bs-target="#introCarousel"
                                data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>

                        </div>
                    </div>

                    <!-- PIE -->
                    <div class="modal-footer border-0 text-center" style="background:#eef3fb;">
                        <button id="test-ok" type="button" class="btn btn-outline-primary w-100 fw-semibold"
                            data-bs-dismiss="modal">
                            Entendido
                        </button>
                    </div>

                </div>
            </div>
        </div>







        <script>
            const SEEN_KEY = "buscotec_intro_seen";
            const url = new URL(window.location.href);
            const force = url.searchParams.get("forceModal") === "1";

            document.addEventListener("DOMContentLoaded", () => {
                const modalEl = document.getElementById("testOnceModal");
                if (!modalEl) return;

                const hasBootstrap = typeof bootstrap !== "undefined" && bootstrap.Modal;

                const showModal = () => {
                    if (hasBootstrap) {
                        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modalInstance.show();

                        modalEl.addEventListener("hidden.bs.modal", () => {
                            localStorage.setItem(SEEN_KEY, String(Date.now()));
                        });

                        const okBtn = document.getElementById("test-ok");
                        if (okBtn) okBtn.addEventListener("click", () => {
                            modalInstance.hide();
                            localStorage.setItem(SEEN_KEY, String(Date.now()));
                        });
                    } else {
                        modalEl.classList.add("show");
                        modalEl.style.display = "block";
                        modalEl.removeAttribute("aria-hidden");
                        localStorage.setItem(SEEN_KEY, String(Date.now()));
                    }
                };

                if (force || !localStorage.getItem(SEEN_KEY)) {
                    showModal();
                }
            });
            // El carrusel se maneja automáticamente vía data-attributes para evitar errores si bootstrap.Carousel falla

        </script>
    <?php endif; ?>


</body>

</html>