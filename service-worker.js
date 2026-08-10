// service-worker.js
// Caches only static assets (css/js/icons). All PHP pages are always
// fetched fresh from the network since this app shows live database data
// (attendance, fees, results) — caching those would show stale info.

const CACHE_NAME = 'sms-shell-v1';
const SHELL_ASSETS = [
  'assets/css/style.css',
  'assets/js/qrcode.min.js',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'manifest.json',
  'offline.php'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Only handle GET requests on our own origin.
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  const isStaticAsset = SHELL_ASSETS.some((asset) => url.pathname.endsWith(asset));

  if (isStaticAsset) {
    // Cache-first for static shell assets — fast repeat loads.
    event.respondWith(
      caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
  } else {
    // Network-first for everything else (all .php pages / live data).
    // Falls back to a friendly offline page only if the network is down.
    event.respondWith(
      fetch(event.request).catch(() => caches.match('offline.php'))
    );
  }
});
