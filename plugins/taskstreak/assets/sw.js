/* TaskStreak Service Worker
 * Goal:
 * - Precache core assets for instant loads
 * - Cache-first for same-origin assets
 * - Network-first for REST API
 * - Navigation fallback: cached page, then offline.html
 */
const CACHE = 'taskstreak-v1-2-0';

const precache = [
  'app.css',
  'app.js',
  'pwa.js',
  'phrases.json',
  'icons/icons.svg',
  'manifest.webmanifest',
  'offline.html'
].map(p => new URL(p, self.registration.scope).toString());

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(precache)).catch(()=>{}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => (k !== CACHE) ? caches.delete(k) : null))).catch(()=>{})
      .then(()=> self.clients.claim())
  );
});

async function cacheFirst(req) {
  const cached = await caches.match(req);
  if (cached) return cached;
  const res = await fetch(req);
  const cache = await caches.open(CACHE);
  cache.put(req, res.clone()).catch(()=>{});
  return res;
}

async function networkFirst(req) {
  try {
    const res = await fetch(req);
    const cache = await caches.open(CACHE);
    cache.put(req, res.clone()).catch(()=>{});
    return res;
  } catch (e) {
    const cached = await caches.match(req);
    if (cached) return cached;
    throw e;
  }
}

self.addEventListener('fetch', (e) => {
  const req = e.request;

  if (req.url.includes('/wp-json/taskstreak/v1/')) {
    e.respondWith(networkFirst(req));
    return;
  }

  if (req.mode === 'navigate') {
    e.respondWith((async () => {
      try {
        const res = await fetch(req);
        const cache = await caches.open(CACHE);
        cache.put(req, res.clone()).catch(()=>{});
        return res;
      } catch (e) {
        const cachedNav = await caches.match(req);
        if (cachedNav) return cachedNav;
        const offline = await caches.match(new URL('offline.html', self.registration.scope).toString());
        return offline || Response.error();
      }
    })());
    return;
  }

  if (new URL(req.url).origin === self.location.origin) {
    e.respondWith(cacheFirst(req));
    return;
  }

  e.respondWith(fetch(req).catch(()=>caches.match(req)));
});
