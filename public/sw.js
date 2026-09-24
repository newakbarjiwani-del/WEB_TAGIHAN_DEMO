/* Tagihan PWA service worker — cache name ikut short brand agar mudah diganti */
const CACHE_VERSION = 'tagihan-pwa-v5';
const SHELL_CACHE = `${CACHE_VERSION}-shell`;
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/css/app.css',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/logo.jpeg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== SHELL_CACHE).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

function isApiOrForm(request, url) {
  if (request.method !== 'GET') return true;
  if (url.pathname.startsWith('/multi-akun')) return true;
  if (url.pathname.startsWith('/generate-va')) return true;
  if (url.pathname.startsWith('/generate-qris')) return true;
  if (url.pathname.startsWith('/cek-tagihan')) return true;
  if (url.pathname.startsWith('/cek-status-pembayaran')) return true;
  if (url.pathname.startsWith('/pembayaran')) return true;
  if (url.pathname.startsWith('/list-tahun-akademik')) return true;
  return false;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (isApiOrForm(request, url)) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          return cached || caches.match(OFFLINE_URL);
        })
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const fetchPromise = fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
          }
          return response;
        })
        .catch(() => cached);

      return cached || fetchPromise;
    })
  );
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    if (event.data) {
      try {
        payload = event.data.json();
      } catch (e1) {
        payload = { body: event.data.text() };
      }
    }
  } catch (e) {
    payload = {};
  }

  const title = payload.title || 'Pembayaran berhasil';
  const options = {
    body: payload.body || 'Transaksi QRIS berhasil diproses.',
    icon: payload.icon || '/icons/icon-192.png',
    badge: payload.badge || '/icons/icon-192.png',
    tag: payload.tag || 'qris-paid',
    renotify: true,
    requireInteraction: false,
    data: Object.assign(
      { url: '/' },
      payload.data || {},
      { url: payload.url || (payload.data && payload.data.url) || '/' }
    ),
  };

  event.waitUntil(
    self.registration.showNotification(title, options).catch(() => {
      return self.registration.showNotification(title, {
        body: options.body,
        tag: options.tag,
      });
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification && event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url && 'focus' in client) {
          client.focus();
          if (client.navigate) {
            try { client.navigate(target); } catch (e) {}
          }
          return;
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(target);
      }
    })
  );
});
