// firebase-messaging-sw.js — BuscoTec FCM Service Worker
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyDNithUfbMvbFW6ZsnKL0WLbSQouwMOFBM",
  authDomain: "buscotec-fadc9.firebaseapp.com",
  projectId: "buscotec-fadc9",
  storageBucket: "buscotec-fadc9.firebasestorage.app",
  messagingSenderId: "278237580879",
  appId: "1:278237580879:web:d7fc07c74cefd8d8abe43a"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('[FCM-SW] Mensaje en segundo plano:', payload);
  const title = payload.notification?.title || "BuscoTec";
  const options = {
    body: payload.notification?.body || "Nuevo mensaje.",
    icon: '/img/icons/icon-192.png',
    badge: '/img/icons/icon-192.png',
    data: payload.data,
    tag: 'buscotec-notif'
  };
  self.registration.showNotification(title, options);
});
