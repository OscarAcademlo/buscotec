<!DOCTYPE html>
<html lang="es">

<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=AW-18059169740">
  </script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'AW-18059169740');
  </script>
  <meta charset="UTF-8" />
  <title>Categoría | BuscoTec</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body,
    html {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      
    }

    .page-wrap {
      height: 100vh;
      
      display: flex;
      flex-direction: column;
    }

    main {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    #map-wrapper {
      flex-grow: 1;
      height: 100%;
      min-height: unset;
      border: none;
      border-radius: 0;
      box-shadow: none;
    }

    .page-wrap {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background-color: #f0f2f5;
      /* fondo general tipo Facebook */
    }

    main {
      flex: 1;
    }

    #map-wrapper {
      position: relative;
      height: calc(100vh - 160px);
      min-height: calc(100vh - 120px);
      border-radius: 0;
      border: none;
      box-shadow: none;
      border-radius: .5rem;
      
      box-shadow: 0 4px 10px rgba(24, 119, 242, 0.15);
      border: 1px solid #c7d4f8;
      /* borde azul claro */
    }

    #map {
      width: 100%;
      height: 100%;
      z-index: 1;
      background: #e9e9e9;
      /* Placeholder color before tiles */
    }

    /* Loader moderno BuscoTec */
    #map-loader {
      position: absolute;
      inset: 0;
      z-index: 9999;
      background: rgba(255, 255, 255, 0.98);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      transition: opacity 0.5s ease;
    }

    .bt-spinner {
      width: 48px;
      height: 48px;
      border: 4px solid #eef3ff;
      border-top: 4px solid #0d6efd;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .bt-loading-text {
      font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      font-weight: 600;
      color: #145dbf;
      font-size: 1.1rem;
      letter-spacing: 0.5px;
      animation: pulse 1.5s infinite ease-in-out;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.6;
      }
    }

    .toolbar {
      gap: .5rem;
    }

    /* ====== Mini cards estilo BuscoTec + Paleta Azul Facebook ====== */
    .leaflet-marker-icon.pc {
      background: transparent;
      border: 0;
    }

    .pc-wrap {
      position: relative;
      transform: translate(-50%, -100%);
      background: #fff;
      border: 1px solid #c7d4f8;
      /* azul suave */
      border-radius: 12px;
      padding: .35rem .55rem;
      min-width: 148px;
      max-width: 240px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, .15);
      font-size: .92rem;
      line-height: 1.15;
      cursor: pointer;
      user-select: none;
      transition: all 0.2s ease-in-out;
    }

    .pc-wrap::after {
      content: "";
      position: absolute;
      left: 50%;
      bottom: -6px;
      width: 12px;
      height: 12px;
      background: #fff;
      border-left: 1px solid #c7d4f8;
      border-bottom: 1px solid #c7d4f8;
      transform: translateX(-50%) rotate(45deg);
    }

    .pc-name {
      font-weight: 700;
      color: #145dbf;
      /* azul medio fuerte */
      word-wrap: break-word;
    }

    .pc-dist {
      font-size: .8rem;
      color: #3b5998;
      /* azul más suave para texto secundario */
    }

    .pc-note {
      font-size: .75rem;
      color: #7a8ca3;
      /* gris azulado */
    }

    /* Estado activo */
    .pc-wrap.active {
      box-shadow: 0 12px 26px rgba(24, 119, 242, 0.3);
      border-color: #1877f2;
      background: #eef3ff;
    }

    /* Estado "más cercano" */
    .pc-wrap.nearest {
      border-color: #1877f2;
      background: #e8f1ff;
    }

    .pc-wrap.nearest .pc-name {
      color: #0b3a82;
    }

    /* Hover sutil */
    .pc-wrap:hover {
      border-color: #145dbf;
      background: #f5f8ff;
      transform: translate(-50%, -102%);
    }

    .status-online {
      font-size: 0.7rem;
      color: #28a745;
      font-weight: bold;
      margin-left: 5px;
      white-space: nowrap;
    }

    .status-online i {
      font-size: 0.5rem;
      vertical-align: middle;
    }
  </style>

</head>

<body class="bg-light">
  <div class="page-wrap">

    <!-- HEADER ESTILO PWA -->
    <header class="bg-primary text-white sticky-top shadow-sm" style="background-color: #0b3558 !important;">
      <div class="container d-flex align-items-center justify-content-between py-3">

        <!-- Botón Volver App-like -->
        <a href="index.php" class="text-white text-decoration-none d-flex align-items-center" style="gap: 8px;">
          <i class="bi bi-arrow-left fs-4"></i>
        </a>

        <!-- Título Centrado -->
        <h5 class="mb-0 fw-bold position-absolute start-50 translate-middle-x w-75 text-center text-truncate"
          id="tituloCategoria">Categorías</h5>

        <!-- Icono secundario (Ayuda/Soporte) -->
        <a href="funciona.html" class="text-white text-decoration-none d-flex align-items-center">
          <i class="bi bi-question-circle fs-5"></i>
        </a>
      </div>

      <!-- Barra de Búsqueda Integrada -->
      <div class="container pb-3 pt-1">
        <div class="position-relative">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
          <input type="text" id="buscadorCategoriaMap" class="form-control rounded-pill border-0 py-2 ps-5 shadow-sm"
            placeholder="Buscar profesional o texto libre..." autocomplete="off">
          <ul id="listaSugerenciasMap" class="list-group position-absolute w-100 mt-1 d-none start-0 text-start"
            style="top: 100%; z-index: 1050; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); max-height: 250px; overflow-y: auto;">
          </ul>
        </div>
      </div>
    </header>


    
    <!-- VISTA: LISTA DE CATEGORIAS (Solo si catId = 0) -->
    <main id="vistaCategorias" class="d-none w-100 p-3" style="background: #f0f2f5; min-height: calc(100vh - 120px); overflow-y: auto; overflow-x: hidden; padding-bottom: 80px !important;">
        <div id="contenedorCategoriasList" class="d-flex flex-column" style="gap: 12px;">
            <div class="text-center text-muted py-5">Cargando categorías...</div>
        </div>
    </main>

    <!-- MAPA -->

    <main id="vistaMapa" class="p-0 m-0 w-100 position-relative">
      <div id="alert" class="alert alert-warning d-none"></div>

      <!-- Overlaid button on map -->
      <button id="btnCentrar" class="btn btn-light btn-sm position-absolute rounded-pill shadow-sm"
        style="bottom: 30px; right: 20px; z-index: 1000; font-weight: 600; color: #0b3558;">
        <i class="bi bi-geo-alt-fill me-1"></i> Mi ubicación
      </button>

      <div id="map-wrapper">

        <div id="map"></div>
        <div id="map-loader">
          <div class="bt-spinner"></div>
          <div class="bt-loading-text">Cargando mapa...</div>
        </div>
      </div>

    </main>

    <!-- NO FOOTER -->
  </div>

  <!-- Modal No hay profesionales -->
  <div class="modal fade" id="modalNoProfesionales" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
        <div class="modal-body text-center p-5">
          <div class="mb-4">
            <i class="bi bi-geo-alt-fill text-primary" style="font-size: 4.5rem; filter: drop-shadow(0 4px 10px rgba(13, 110, 253, 0.2));"></i>
          </div>
          <h4 class="fw-bold mb-3" style="color: #0b3558;">Aún no hay profesionales en tu zona</h4>
          <p class="text-muted mb-4">Todavía no tenemos prestadores registrados en esta categoría cerca de tu ubicación actual.</p>
          
          <div class="d-grid gap-2">
            <a href="categoria.php" class="btn btn-primary rounded-pill py-3 fw-bold">
               Seguir buscando
            </a>
            <a href="categoria.php" class="btn btn-outline-secondary rounded-pill py-3 fw-bold">
               Ver otras categorías
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>


    const API = "backend/get_profesionales_por_categoria.php";
    const params = new URLSearchParams(location.search);
    const catId = parseInt(params.get("id") || "0", 10);
    const catName = params.get("nombre") || "Todos los Profesionales";
    document.getElementById("tituloCategoria").textContent = catName;

    const map = L.map('map', { zoomControl: false });
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?v=4', {
      attribution: '&copy; OpenStreetMap, &copy; CARTO', subdomains: 'abcd', maxZoom: 20
    }).addTo(map);

    // 1. Init inmediato (mejora percepción de velocidad)
    let userPos = { lat: -41.133472, lng: -71.310278 };
    map.setView([userPos.lat, userPos.lng], 13);

    let userMarker = null;
    const profMarkers = [];
    let sesionActiva = false;

    // icono usuario
    const userIcon = L.icon({
      iconUrl: 'data:image/svg+xml;utf8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="32" height="48"><path d="M16 0C7.2 0 0 7.2 0 16c0 11.2 16 32 16 32s16-20.8 16-32C32 7.2 24.8 0 16 0z" fill="#0d6efd"/><circle cx="16" cy="16" r="7" fill="#fff"/></svg>`),
      iconSize: [24, 36], iconAnchor: [12, 36]
    });

    
    const isGlobal = (catId === 0 || isNaN(catId));
    
    // Icon mapping and color mapping for categories (matching index.php)
    const iconMap = {
        'electricista': 'bi-lightning-fill',
        'plomero': 'bi-droplet-fill',
        'cerrajero': 'bi-key-fill',
        'carpintero': 'bi-hammer',
        'gasista': 'bi-fire',
        'albañil': 'bi-bricks',
        'pintor': 'bi-paint-bucket',
        'flete': 'bi-truck',
        'jardinero': 'bi-tree-fill',
        'limpieza': 'bi-stars',
        'mecanico': 'bi-gear-wide-connected',
        'peluquero': 'bi-scissors',
        'manicura': 'bi-hand-index-thumb-fill',
        'viandas': 'bi-egg-fried',
        'clases': 'bi-book-fill',
        'abogado': 'bi-bank'
    };
    
    const colorMap = {
        'electricista': '#fff9c4', 'plomero': '#e1f5fe', 'cerrajero': '#f3e5f5',
        'carpintero': '#efebe9', 'gasista': '#fbe9e7', 'albañil': '#e0f2f1',
        'pintor': '#fce4ec', 'flete': '#e8eaf6', 'jardinero': '#e8f5e9',
        'limpieza': '#f0f4c3', 'mecanico': '#f5f5f5', 'peluquero': '#fdf2f2',
        'manicura': '#fdf2f2', 'viandas': '#fff3e0', 'clases': '#e0f7fa',
        'abogado': '#eceff1'
    };

    if (isGlobal) {
        document.getElementById('vistaMapa').classList.add('d-none');
        document.getElementById('vistaCategorias').classList.remove('d-none');
        document.getElementById('buscadorCategoriaMap').placeholder = "Buscar categoría...";
        document.getElementById('tituloCategoria').textContent = "Categorías";
        
        // Hide loader because map is not used
        document.getElementById('map-loader').style.display = 'none';

        // Fetch all categories and render them
        fetch("backend/get_categorias.php")
            .then(res => res.json())
            .then(data => {
                const categorias = Array.isArray(data.items) ? data.items : [];
                window.allCategoriasLoaded = categorias;
                renderCategoriasList(categorias);
            })
            .catch(err => console.error(err));
            
        // Modificar buscador para categorías
        const searchInput = document.getElementById('buscadorCategoriaMap');
        searchInput.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase().trim();
            if(!window.allCategoriasLoaded) return;
            
            const filtrados = window.allCategoriasLoaded.filter(c => c.nombre.toLowerCase().includes(val));
            renderCategoriasList(filtrados);
        });
        
    } else {
        // Vista modo mapa
        document.getElementById('tituloCategoria').textContent = catName;
    }
    
    function renderCategoriasList(categorias) {
        const listDiv = document.getElementById('contenedorCategoriasList');
        if (!categorias || categorias.length === 0) {
            listDiv.innerHTML = '<div class="text-center text-muted py-5">No se encontraron categorías.</div>';
            return;
        }
        
        listDiv.innerHTML = '';
        categorias.forEach(cat => {
            const normalized = cat.nombre.toLowerCase();
            let keyMatch = "default";
            for (const k in iconMap) { if (normalized.includes(k)) { keyMatch = k; break; } }
            const iconClass = iconMap[keyMatch] || 'bi-wrench';
            const bgColor = colorMap[keyMatch] || '#e9ecef';

            const a = document.createElement('a');
            a.href = `categoria.php?id=${cat.id}&nombre=${encodeURIComponent(cat.nombre)}`;
            a.className = "d-flex align-items-center justify-content-between p-3 bg-white text-decoration-none text-dark shadow-sm";
            a.style.borderRadius = "12px";
            
            a.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex justify-content-center align-items-center" style="width: 45px; height: 45px; border-radius: 12px; background-color: ${bgColor};">
                        <i class="bi ${iconClass} fs-4 text-primary"></i>
                    </div>
                    <span class="fw-bold" style="font-size: 1.1rem; color: #333;">${cat.nombre}</span>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
            `;
            listDiv.appendChild(a);
        });
    }



    // 2. Lógica optimizada: Paralelizar Geo + Render inicial
    if (!isGlobal) { initMapFlow(); }
    async function initMapFlow() {
      try {
        const mostrarUbicacion = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';

        // Promesa de Geolocation con timeout y alta precisión
        const getGeo = new Promise(resolve => {
          if (!navigator.geolocation || !mostrarUbicacion) return resolve(null);
          navigator.geolocation.getCurrentPosition(
            pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            err => { console.log('Geo err:', err); resolve(null); },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
          );
        });

        // Esperamos ubicación real o timeout
        const realPos = await getGeo;
        if (realPos) {
          userPos = realPos;
          localStorage.setItem('buscotec_last_lat', realPos.lat);
          localStorage.setItem('buscotec_last_lng', realPos.lng);
        } else if (mostrarUbicacion) {
          const savedLat = parseFloat(localStorage.getItem('buscotec_last_lat'));
          const savedLng = parseFloat(localStorage.getItem('buscotec_last_lng'));
          if (!isNaN(savedLat) && !isNaN(savedLng)) {
            userPos = { lat: savedLat, lng: savedLng };
          }
        }

        // Actualizamos marcador y vista
        putUserMarker();

        // Cargamos profesionales
        await loadProfessionals();

      } catch (e) { console.error(e); }

      // 3. Ocultar loader finalmente
      const loader = document.getElementById('map-loader');
      if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => loader.style.display = 'none', 500);
      }
    }

    // Iniciar flujo
    // initMapFlow is now conditionally called above


    // Boton mi ubicacion
    const btnCentrar = document.getElementById('btnCentrar');
    if (btnCentrar) {
      btnCentrar.addEventListener('click', () => {
        if (userPos && map) {
          map.setView([userPos.lat, userPos.lng], 14);
        }
      });
    }

    function putUserMarker() {
      const mostrarUbicacion = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
      if (!mostrarUbicacion) {
        if (userMarker) {
          map.removeLayer(userMarker);
          userMarker = null;
        }
        return;
      }
      if (userMarker) return userMarker.setLatLng(userPos);
      userMarker = L.marker([userPos.lat, userPos.lng], { icon: userIcon }).addTo(map);
      map.setView(userPos, 13);
    }

    // helpers
    const toRad = d => d * Math.PI / 180;
    function distKm(a, b) {
      const R = 6371, dLat = toRad(b.lat - a.lat), dLon = toRad(b.lng - a.lng);
      const la1 = toRad(a.lat), la2 = toRad(b.lat);
      const h = Math.sin(dLat / 2) ** 2 + Math.cos(la1) * Math.cos(la2) * Math.sin(dLon / 2) ** 2;
      return 2 * R * Math.asin(Math.sqrt(h));
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c])) }

    function makeCardIcon(nombre, distKm, id, estado, classes = '') {
      const distTxt = Number.isFinite(distKm) ? `${distKm.toFixed(1)} km` : '';
      const onlineTag = (estado === 1) ? '<span class="status-online"><i class="bi bi-circle-fill"></i> Online</span>' : '';

      return L.divIcon({
        className: 'pc',
        html: `<div class="pc-wrap ${classes}" data-id="${id}">
            <div class="pc-name d-flex justify-content-between align-items-center">
              <span>${esc(nombre)}</span>
              ${onlineTag}
            </div>
            <div class="pc-dist">${distTxt}</div>
            <div class="pc-note">zona aproximada</div>
          </div>`,
        iconSize: [0, 0], iconAnchor: [0, 0]
      });
    }
    function setClassOnMarkerEl(marker, cls, enabled) {
      const el = marker.getElement(); if (!el) return;
      const wrap = el.querySelector('.pc-wrap'); if (!wrap) return;
      wrap.classList.toggle(cls, !!enabled);
    }


    // Nueva Logica Buscador
    
    // Nueva Logica Buscador (Mapa o Categorías)
    const searchInput = document.getElementById('buscadorCategoriaMap');
    const sugerenciasMap = document.getElementById('listaSugerenciasMap');

    if (searchInput && !isGlobal) {

      searchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase().trim();
        sugerenciasMap.innerHTML = '';

        if (!val) {
          sugerenciasMap.classList.add('d-none');
          renderMarkers(window.allProfesionalesLoaded || []); // Restaurar si borra todo
          return;
        }

        // Filto local sobre los datos de los marcadores ya cargados
        if (window.allProfesionalesLoaded) {
          const filtrados = window.allProfesionalesLoaded.filter(p => {
            return (p.nombre && p.nombre.toLowerCase().includes(val)) ||
              (p.rubro_nombre && p.rubro_nombre.toLowerCase().includes(val)) ||
              (p.descripcion && p.descripcion.toLowerCase().includes(val));
          });

          renderMarkers(filtrados);

          if (filtrados.length === 0) {
            sugerenciasMap.innerHTML = '<li class="list-group-item text-muted">No se encontraron profesionales con ese término</li>';
            sugerenciasMap.classList.remove('d-none');
          } else {
            sugerenciasMap.classList.add('d-none');
          }
        }
      });

      // Ocultar sugerencias si hace click fuera
      document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !sugerenciasMap.contains(e.target)) {
          sugerenciasMap.classList.add('d-none');
        }
      });
    }


    async function loadProfessionals() {
      try {
        const res = await fetch(`${API}?id=${catId}&lat=${userPos.lat}&lng=${userPos.lng}`);

        const data = await res.json();
        if (!data.ok) return;

        window.allProfesionalesLoaded = data.profesionales; // Guardar en ventana para permitir filtrar localmente
        
        // --- VERIFICACIÓN DE ZONA ---
        // Consideramos que "no hay en la zona" si el más cercano está a más de 50km
        // o si la lista directamente está vacía.
        const LIMITE_ZONA_KM = 50; 
        const hayEnZona = data.profesionales.some(p => p.distancia <= LIMITE_ZONA_KM);
        
        if (!hayEnZona) {
           const modalEl = document.getElementById('modalNoProfesionales');
           if (modalEl) {
              const modal = new bootstrap.Modal(modalEl);
              modal.show();
           }
        }

        renderMarkers(data.profesionales);
      } catch (e) { console.error(e); }
    }


    function renderMarkers(items) {
      profMarkers.forEach(p => map.removeLayer(p.marker));
      profMarkers.length = 0;

      if (!items.length) return;

      items.forEach(p => {
        const lat = parseFloat(p.lat), lng = parseFloat(p.lng);
        if (!lat || !lng) return;
        const nombre = p.nombre || 'Profesional';
        let d = null; if (userPos) d = distKm(userPos, { lat, lng });

        const mk = L.marker([lat, lng], {
          icon: makeCardIcon(nombre, d, p.id, p.estado_servicio)
        }).addTo(map);

        mk.on('mouseover', () => setClassOnMarkerEl(mk, 'active', true));
        mk.on('mouseout', () => setClassOnMarkerEl(mk, 'active', false));
        mk.on('click', () => abrirContacto(p.id));

        profMarkers.push({ marker: mk, distKm: d });
      });

      if (profMarkers.length) {
        const g = L.featureGroup(profMarkers.map(p => p.marker));
        map.fitBounds(g.getBounds().pad(0.2));
      }
    }

    /* ==========================
       NAVBAR Y SESIÓN REAL
       ========================== */
    /* ==========================
       NAVBAR Y SESIÓN REAL (CON RESTAURACIÓN)
       ========================== */
    (async () => {
      // 1. Intentar restaurar sesión si hay datos locales (Fix Session Persistence)
      const id = localStorage.getItem('buscotec_user_id');
      const email = localStorage.getItem('buscotec_email');
      const role = localStorage.getItem('buscotec_role');

      if (id && email) {
        try {
          await fetch('/backend/sesion_estable.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${encodeURIComponent(id)}&email=${encodeURIComponent(email)}&role=${encodeURIComponent(role || 'usuario')}`
          });
        } catch (e) {
          console.warn("Falló restauración de sesión", e);
        }
      }

      // 2. Verificar estado final
      try {
        const res = await fetch('/backend/get_sesion.php', { credentials: 'include' });
        const data = await res.json();
        const nav = document.getElementById('navList');

        if (data?.data?.email) {
          sesionActiva = true;
          const nombre = data.data.nombre || data.data.email;

          if (nav) {
            nav.innerHTML = `
        <!-- Nombre del usuario -->
        <li class="nav-item">
          <a class="nav-link disabled text-white">${nombre}</a>
        </li>

        <!-- Botón Ubicación -->
        <li class="nav-item d-flex align-items-center">
          <button id="btnToggleUbicacion" class="btn btn-sm text-white ms-2 px-2 py-0 align-middle rounded-pill fw-bold border border-white-50" style="background: rgba(255,255,255,0.25); font-size: 0.75rem;" title="Mostrar u ocultar mi ubicación actual" onclick="toggleUbicacionUsuario()">
              <i class="bi bi-geo-alt-fill text-warning" id="icoUbicacion"></i> <span id="lblUbicacion">📍 Ubicación: Visible</span>
          </button>
        </li>

        <!-- Ícono de ayuda -->
        <li class="nav-item">
          <a class="nav-link text-warning fs-5" href="funciona.html" title="Cómo funciona">
            <i class="bi bi-question-circle-fill"></i>
          </a>
        </li>

        <!-- Cerrar sesión -->
        <li class="nav-item">
          <a class="nav-link text-danger" href="#" onclick="cerrarSesion()">Cerrar sesión</a>
        </li>
      `;
          }
          // Actualizar UI del botón de ubicación al cargar
          updateUbicacionUI();

          const userId = localStorage.getItem('buscotec_user_id') || data.data.id;
          const role = localStorage.getItem('buscotec_role') || data.data.role;
          if (userId && role) {
            postGeolocation(userId, role);
          }
        } else {
          sesionActiva = false;
        }

      } catch (e) {
        console.warn("Error al verificar sesión:", e);
      }
    })();

    function updateUbicacionUI() {
      const mostrar = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
      const txt = mostrar ? '📍 Ubicación: Visible' : '🙈 Ubicación: Oculta';
      const bgStyle = mostrar ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.1)';
      const icoClass = mostrar ? 'bi bi-geo-alt-fill text-warning' : 'bi bi-eye-slash-fill text-light';

      const el = document.getElementById('lblUbicacion');
      if (el) el.textContent = txt;

      const ico = document.getElementById('icoUbicacion');
      if (ico) ico.className = icoClass;

      const btn = document.getElementById('btnToggleUbicacion');
      if (btn) btn.style.background = bgStyle;
    }

    function toggleUbicacionUsuario() {
      const estadoActual = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
      const nuevoEstado = !estadoActual;
      localStorage.setItem('buscotec_mostrar_ubicacion', nuevoEstado ? '1' : '0');
      updateUbicacionUI();

      const userId = localStorage.getItem('buscotec_user_id');
      const role = localStorage.getItem('buscotec_role');

      if (!nuevoEstado && userId && role) {
        fetch('/backend/registrar_ubicacion.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&action=delete`
        }).then(() => {
          location.reload();
        }).catch(() => { });
      } else if (nuevoEstado && userId && role) {
        navigator.geolocation.getCurrentPosition((pos) => {
          const { latitude, longitude } = pos.coords || {};
          if (latitude && longitude) {
            // Validar que la posición esté realmente en la región de Bariloche/Patagonia (evita falsos saltos de ISP/Satélites a BSAS)
            if (latitude >= -42.5 && latitude <= -39.5 && longitude >= -72.5 && longitude <= -70.5) {
              localStorage.setItem('buscotec_last_lat', String(latitude));
              localStorage.setItem('buscotec_last_lng', String(longitude));
              fetch('/backend/registrar_ubicacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&lat=${encodeURIComponent(latitude)}&lng=${encodeURIComponent(longitude)}`
              }).then(() => {
                location.reload();
              }).catch(() => { });
            } else {
              alert("Ubicación detectada fuera de Bariloche (IP Satelital/Buenos Aires). Se mantendrá tu última posición registrada o el centro de Bariloche.");
              location.reload();
            }
          }
        }, () => {
          location.reload();
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
      } else {
        location.reload();
      }
    }

    function postGeolocation(userId, role) {
      if (!('geolocation' in navigator) || !userId) return;
      const mostrarUbicacion = localStorage.getItem('buscotec_mostrar_ubicacion') !== '0';
      if (!mostrarUbicacion) return;

      navigator.geolocation.getCurrentPosition((pos) => {
        const { latitude, longitude } = pos.coords || {};
        if (latitude && longitude) {
          // Validar que la posición esté realmente en Bariloche
          if (latitude >= -42.5 && latitude <= -39.5 && longitude >= -72.5 && longitude <= -70.5) {
            localStorage.setItem('buscotec_last_lat', String(latitude));
            localStorage.setItem('buscotec_last_lng', String(longitude));
            fetch('/backend/registrar_ubicacion.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `user_id=${encodeURIComponent(userId)}&rol=${encodeURIComponent(role)}&lat=${encodeURIComponent(latitude)}&lng=${encodeURIComponent(longitude)}`
            }).catch(() => { });
          }
        }
      }, () => { }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    }


    /* ==========================
       CONTACTAR
       ========================== */
    function abrirContacto(id) {
      // Estrategia Robustez: Si ya validamos sesión O si el usuario tiene datos locales, dejamos pasar.
      // Esto evita que un retraso en la red te mande al login incorrectamente.
      const localId = localStorage.getItem('buscotec_user_id');

      if (sesionActiva || localId) {
        location.href = `perfil_profesional.html?id=${id}`;
      } else {
        location.href = 'login.html';
      }
    }

    /* ==========================
       CERRAR SESIÓN
       ========================== */
    function cerrarSesion() {
      fetch('backend/logout.php', { credentials: 'include' })
        .then(() => localStorage.clear())
        .finally(() => location.href = 'index.php');
    }
  </script>

</body>

</html>