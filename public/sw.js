/* Taskly — Service Worker. Offline-Shell (architecture.md §6). */
const CACHE = 'taskly-v7';
const SHELL = [
  '/',
  '/index.html',
  '/assets/css/styles.css',
  '/assets/js/app.js',
  '/assets/img/tanuki/base-neutral.png',
  '/assets/img/tanuki/base-happy.png',
  '/assets/img/tanuki/base-celebrate.png',
  '/assets/img/tanuki/base-tired.png',
  '/assets/img/tanuki/icon-192.png',
  '/manifest.webmanifest',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const { request } = e;
  // API immer aus dem Netz (nie cachen).
  if (request.method !== 'GET' || new URL(request.url).pathname.startsWith('/api/')) {
    return;
  }
  // Statische Shell: cache-first, mit Netz-Fallback.
  e.respondWith(
    caches.match(request).then(hit => hit || fetch(request).then(res => {
      const copy = res.clone();
      caches.open(CACHE).then(c => c.put(request, copy)).catch(() => {});
      return res;
    }).catch(() => caches.match('/index.html')))
  );
});
