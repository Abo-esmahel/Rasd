const CACHE_STATIC = 'rasd-static-v3';
const CACHE_API = 'rasd-api-v1';
const STATIC_ASSETS = [
  '/pwa/',
  '/pwa/index.html',
  '/pwa/css/app.css',
  '/pwa/js/app.js',
  '/pwa/js/api.js',
  '/pwa/js/auth.js',
  '/pwa/js/screens.js',
  '/pwa/manifest.json',
  '/pwa/icons/icon-192.png',
  'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap'
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE_STATIC).then(c => c.addAll(STATIC_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== CACHE_STATIC && k !== CACHE_API).map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  if (url.pathname.startsWith('/api/')) {
    // API: network-first with cache fallback
    // المرفقات (/api/attachments) ثنائية من Cloudinary — لا تُحفظ على الجهاز أبدًا (network فقط)
    const isAttachment = url.pathname.startsWith('/api/attachments');
    if (e.request.method === 'GET') {
      e.respondWith(
        fetch(e.request).then(resp => {
          if (resp.ok && !isAttachment) {
            const clone = resp.clone();
            caches.open(CACHE_API).then(c => c.put(e.request, clone));
          }
          return resp;
        }).catch(() => caches.match(e.request))
      );
    } else {
      // POST/PUT/DELETE: network only, queue for background sync
      e.respondWith(
        fetch(e.request).catch(() => {
          if (e.request.method !== 'GET') {
            return new Response(JSON.stringify({
              success: false, message: 'لا يوجد اتصال بالإنترنت'
            }), { headers: { 'Content-Type': 'application/json' }, status: 503 });
          }
          return caches.match(e.request);
        })
      );
    }
  } else {
    // Static: cache-first — باستثناء المرفقات (/attachments) فهي من Cloudinary ولا تُحفظ على الجهاز
    if (url.pathname.startsWith('/attachments/')) {
      e.respondWith(fetch(e.request));
      return;
    }
    e.respondWith(
      caches.match(e.request).then(r => {
        if (r) return r;
        return fetch(e.request).then(resp => {
          if (resp.ok && e.request.url.startsWith(self.location.origin)) {
            const clone = resp.clone();
            caches.open(CACHE_STATIC).then(c => c.put(e.request, clone));
          }
          return resp;
        });
      })
    );
  }
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.matchAll({ type: 'window' }).then(list => {
    for (const c of list) { if (c.url.includes('/pwa/') && 'focus' in c) return c.focus(); }
    return clients.openWindow('/pwa/');
  }));
});
