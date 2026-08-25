// sw.js — Service Worker BuscoTec [v10.0 PWA]

importScripts('https://cdn.webpushr.com/sw-server.min.js');

const VERSION = "v10.0";
const CACHE_NAME = `buscotec-cache-${VERSION}`;
const STATIC_FILES = [
  "/",
  "/index.php",
  "/manifest.json",
  "/img/logo_web.png",
  "/img/icons/icon-192.png",
  "/img/icons/icon-512.png",
  "/offline.html"
];

/* ============================
   🧩 Instalación
============================ */
self.addEventListener("install", (event) => {
  console.log(`✅ Instalando SW BuscoTec ${VERSION}`);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_FILES))
      .catch(err => console.warn("⚠️ Error en precache:", err))
  );
  self.skipWaiting();
});

/* ============================
   🔄 Activación y limpieza
============================ */
self.addEventListener("activate", (event) => {
  console.log(`✅ Activando SW ${VERSION}, limpiando versiones viejas...`);
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

/* ============================
   🌐 Manejo de Peticiones (Fetch)
============================ */
self.addEventListener("fetch", (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // 1. Exclusiones críticas: Backend, PHP (procesado por servidor), Webpushr, y POSTs
  if (
    req.method !== "GET" ||
    url.pathname.startsWith("/backend/") ||
    url.pathname.endsWith(".php") ||
    url.pathname === "/index.php" ||
    url.pathname.endsWith("/sw.js") ||
    req.mode === "cors"
  ) {
    return;
  }

  // 2. Estrategia diferenciada para Navegaciones (Páginas completas) vs Assets
  if (req.mode === "navigate") {
    // 🚀 NETWORK FIRST: Intentamos red siempre, si falla usamos cache, si no offline.html
    // Esto evita quedarse "pegado" en la página de offline si recuperamos conexión.
    event.respondWith(
      fetch(req)
        .then((netRes) => {
          if (netRes && netRes.ok) {
            const clone = netRes.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(req, clone));
          }
          return netRes;
        })
        .catch(() => {
          console.warn("⚠️ Error de red en navegación, buscando en cache...");
          return caches.match(req).then(cachedRes => {
            return cachedRes || caches.match("/offline.html");
          });
        })
    );
  } else {
    // 📦 CACHE FIRST: Para imágenes, CSS, JS, etc. Mejoramos velocidad.
    event.respondWith(
      caches.match(req).then((cachedRes) => {
        if (cachedRes) return cachedRes;

        return fetch(req)
          .then((netRes) => {
            // Solo cachear recursos válidos del propio origen
            if (netRes && netRes.ok && netRes.type === "basic") {
              const clone = netRes.clone();
              caches.open(CACHE_NAME).then(cache => cache.put(req, clone));
            }
            return netRes;
          })
          .catch(() => {
            // Si falla un asset, NO devolvemos offline.html porque rompería el layout
            // Simplemente devolvemos una respuesta vacía o error 404
            return new Response("", { status: 404, statusText: "Offline Asset" });
          });
      })
    );
  }
});

/* ==========================================================
   🔔 Click en notificación → abrir la URL correcta
========================================================== */
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const data = event.notification?.data || {};
  let targetUrl = data.url;
  const msgId = data.messageId || data.msgId || data.id || null;

  if (!targetUrl) {
    if (msgId) {
      const rol = (data.rol || "profesional").toLowerCase();
      const tipo = (data.tipo || "recibidos").toLowerCase();
      const u = new URL("/mensajes.html", self.location.origin);
      u.searchParams.set("tipo", tipo === "enviados" ? "enviados" : "recibidos");
      u.searchParams.set("rol", rol === "usuario" ? "usuario" : "profesional");
      u.searchParams.set("open", String(msgId));
      targetUrl = u.toString();
    } else {
      targetUrl = `${self.location.origin}/mensajes.html`;
    }
  }

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true })
      .then((clients) => {
        const sameOrigin = clients.find(c => c.url && c.url.startsWith(self.location.origin));
        if (sameOrigin) {
          sameOrigin.focus();
          sameOrigin.navigate(targetUrl);
          return;
        }
        return self.clients.openWindow(targetUrl);
      })
  );
});

/* ==========================================================
   📩 PUSH NATIVO (FALLBACK)
========================================================== */
self.addEventListener('push', (event) => {
  console.log('⚡ [SW] Evento Push Recibido', event);

  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { message: event.data.text() };
    }
  }

  const title = data.title || "BuscoTec";
  const options = {
    body: data.message || data.body || "Tienes un nuevo mensaje.",
    icon: data.icon || "/img/icons/icon-192.png",
    badge: "/img/icons/icon-192.png",
    vibrate: [200, 100, 200],
    data: {
      url: data.target_url || data.url || "/mensajes.html",
      messageId: data.messageId,
      rol: data.rol
    },
    tag: 'buscotec-msg',
    renotify: true
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

console.log(`🚀 SW BuscoTec activo ${VERSION}`);


console.log(`🚀 SW BuscoTec activo ${VERSION}`);