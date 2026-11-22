// Nipange service worker — static shell + offline fallback only.
// NEVER caches API responses, messages, or account pages.
const CACHE = 'nipange-v18';
const STATIC = [
  '/assets/style.css?v=14',
  '/assets/app.js?v=10',
  '/assets/alpine.min.js?v=1',
  '/assets/icons/icon-192.png',
  '/offline.html',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET') return;
  // never intercept API calls, uploads, or cross-origin
  if (url.origin !== location.origin || url.pathname.startsWith('/api/')) return;

  // HTML navigations: network-first, offline fallback page
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).catch(() => caches.match('/offline.html'))
    );
    return;
  }
  // static assets + immutable image thumbs: cache-first
  if (/\.(css|js|png|jpg|webp|woff2?)$/.test(url.pathname) || url.pathname === '/img.php') {
    e.respondWith(
      caches.match(e.request).then(hit =>
        hit || fetch(e.request).then(res => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then(c => c.put(e.request, copy));
          }
          return res;
        })
      )
    );
  }
});
