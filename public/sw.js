/* Tagihan PWA service worker — jangan cache HTML (CSRF / sesi) */
const CACHE_VERSION = 'tagihan-pwa-v11';
const SHELL_CACHE = `${CACHE_VERSION}-shell`;
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/css/app.css',
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
  if (url.pathname.startsWith('/history-topup-qris')) return true;
  if (url.pathname.startsWith('/pembayaran')) return true;
  if (url.pathname.startsWith('/list-tahun-akademik')) return true;
  if (url.pathname.startsWith('/push/')) return true;
  if (url.pathname.startsWith('/csrf-token')) return true;
  return false;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (isApiOrForm(request, url)) return;

  // Navigate = halaman Blade berisi CSRF — selalu network-first, JANGAN cache HTML
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request, { cache: 'no-store' }).catch(async () => {
        return (await caches.match(OFFLINE_URL)) || Response.error();
      })
    );
    return;
  }

  // Aset statis saja yang boleh di-cache
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

function absUrl(path) {
  if (!path) return self.location.origin + '/icons/icon-192.png';
  if (/^https?:\/\//i.test(path)) return path;
  return self.location.origin + (path.charAt(0) === '/' ? path : '/' + path);
}

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    if (event.data) {
      try {
        payload = event.data.json();
      } catch (e1) {
        payload = { body: String(event.data.text() || '') };
      }
    }
  } catch (e) {
    payload = {};
  }

  const title = payload.title || 'Pembayaran berhasil';
  const body = payload.body || 'Transaksi QRIS berhasil diproses.';
  const tag = payload.tag || ('qris-paid-' + Date.now());
  const targetUrl = payload.url || (payload.data && payload.data.url) || '/';

  // Icon opsional — kalau gagal load, notifikasi tetap harus tampil
  const options = {
    body,
    tag,
    renotify: false,
    requireInteraction: false,
    silent: false,
    data: Object.assign({ url: targetUrl }, payload.data || {}, { url: targetUrl }),
  };
  if (payload.icon) options.icon = absUrl(payload.icon);
  if (payload.badge) options.badge = absUrl(payload.badge);

  event.waitUntil(
    self.registration.showNotification(title, options).catch(() =>
      self.registration.showNotification(title, { body, tag, data: { url: targetUrl } })
    )
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification && event.notification.data && event.notification.data.url) || '/';
  const abs = absUrl(target);
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url && 'focus' in client) {
          client.focus();
          if (client.navigate) {
            try { client.navigate(abs); } catch (e) {}
          }
          return;
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(abs);
      }
    })
  );
});

self.addEventListener('pushsubscriptionchange', (event) => {
  // Biarkan halaman login/dashboard re-subscribe; jaga SW tetap hidup
  event.waitUntil(Promise.resolve());
});
