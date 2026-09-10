const CACHE_STATIC = 'rasd-root-v15-avatar-fix';
const CACHE_API = 'rasd-api-v1';
const STATIC_ASSETS = [
  '/',
  '/offline.html',
  '/manifest.json',
  '/pwa/icons/icon-192.png',
  '/pwa/icons/icon-512.png',
  '/pwa/icons/icon-maskable-512.png'
];

const NEVER_CACHE_PATTERNS = [
  '/notifications/stream',
  '/notifications/feed',
  '/notifications/unread-count',
  '/livewire',
  '/broadcasting/auth',
  '/_diag',
  '/storage/'
];

function isNeverCache(url) {
  for (const p of NEVER_CACHE_PATTERNS) {
    if (url.pathname.startsWith(p)) return true;
  }
  return false;
}

self.addEventListener('install', e => {
  console.log('[SW root] install v11-fix-loader-freeze scope /');
  e.waitUntil(
    caches.open(CACHE_STATIC).then(function(cache){
      return Promise.allSettled(STATIC_ASSETS.map(function(url){
        return cache.add(new Request(url, {cache: 'reload'})).catch(function(err){
          console.warn('[SW root] cache add failed for', url, err);
        });
      }));
    }).then(function(){ return self.skipWaiting(); })
  );
});

self.addEventListener('activate', e => {
  console.log('[SW root] activate v11-fix-loader-freeze');
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== CACHE_STATIC && k !== CACHE_API).map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('message', e => {
  if (e.data && e.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (url.origin !== self.location.origin) return;
  if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
  if (isNeverCache(url)) return;
  if (e.request.headers.has('range')) return;

  
  if (e.request.method !== 'GET') {
    if (url.pathname.startsWith('/api/')) {
      e.respondWith(
        fetch(e.request).catch(() => {
          return new Response(JSON.stringify({
            success: false, message: 'Ù„Ø§ ÙŠÙˆØ¬Ø¯ Ø§ØªØµØ§Ù„ Ø¨Ø§Ù„Ø¥Ù†ØªØ±Ù†Øª'
          }), { headers: { 'Content-Type': 'application/json' }, status: 503 });
        })
      );
    }
    return;
  }

  
  if (url.pathname.startsWith('/api/')) {
    const isAttachment = url.pathname.startsWith('/api/attachments') || url.pathname.includes('/attachments/');
    const isAuthPrivate = url.pathname === '/api/me' || url.pathname === '/api/logout';
    e.respondWith(
      fetch(e.request).then(resp => {
        if (resp.ok && !isAttachment && !isAuthPrivate) {
          const cc = resp.headers.get('Cache-Control') || '';
          if (!cc.includes('no-store') && !cc.includes('private')) {
            const clone = resp.clone();
            caches.open(CACHE_API).then(c => c.put(e.request, clone));
          }
        }
        return resp;
      }).catch(() => caches.match(e.request))
    );
    return;
  }

  
  if (url.pathname.startsWith('/attachments/') || url.pathname.startsWith('/submission-attachments/')) {
    e.respondWith(fetch(e.request));
    return;
  }

  
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).then(resp => {
        
        return resp;
      }).catch(() => {
        
        return caches.match(e.request).then(r => r || caches.match('/')).then(r => r || caches.match('/offline.html')).then(r => r || new Response('Offline', {status: 503, headers:{'Content-Type':'text/html'}}));
      })
    );
    return;
  }

  
  if (
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/pwa/') ||
    url.pathname === '/manifest.json' ||
    url.pathname === '/offline.html' ||
    url.pathname === '/favicon.ico' ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.woff') ||
    url.pathname.endsWith('.woff2')
  ) {
    e.respondWith(
      caches.match(e.request).then(r => {
        if (r) return r;
        return fetch(e.request).then(resp => {
          if (resp.ok) {
            const clone = resp.clone();
            caches.open(CACHE_STATIC).then(c => c.put(e.request, clone));
          }
          return resp;
        }).catch(() => caches.match('/offline.html'));
      })
    );
    return;
  }

  
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});

self.addEventListener('push', e => {
  console.log('[SW] push received', e.data ? e.data.text().substring(0,200) : 'no data');
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch { try{ data = JSON.parse(e.data.text()); }catch{ data={}; } }
  const title = data.title || data.data?.title || 'إشعار جديد';
  const body = data.body || data.message || data.data?.message || 'لديك إشعار جديد';
  const url = data.url || data.data?.url || '/notifications';
  const tag = data.tag || 'rasd-' + Date.now();
  e.waitUntil(self.registration.showNotification(title, {
    body: body.substring(0,180),
    icon: '/pwa/icons/icon-192.png',
    badge: '/pwa/icons/icon-192.png',
    tag: tag,
    requireInteraction: true,
    vibrate: [250, 100, 250],
    data: { url: url }
  }));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = e.notification.data?.url || '/';
  e.waitUntil(clients.matchAll({ type: 'window' }).then(list => {
    for (const c of list) { if ('focus' in c) return c.focus(); }
    return clients.openWindow(url);
  }));
});


