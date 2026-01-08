/*
 * PWA service worker (intranet-safe): caches assets only.
 * - Never caches HTML/pages.
 * - Navigation requests are network-first with offline fallback.
 */

const CACHE_NAME = 'famille-assets-v3';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      await cache.addAll([OFFLINE_URL]);
      await self.skipWaiting();
    })()
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)));
      await self.clients.claim();
    })()
  );
});

function isAssetRequest(request, url) {
  if (request.method !== 'GET') return false;
  if (url.origin !== self.location.origin) return false;

  // Explicit allowlist: built assets + static images/icons.
  if (url.pathname.startsWith('/build/')) return true;
  if (url.pathname.startsWith('/images/')) return true;

  // Common asset extensions (served by the web server, not Laravel).
  return /\.(?:css|js|mjs|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|otf)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Never intercept non-GET except navigation fallback below.

  // Navigation: do NOT cache HTML.
  // IMPORTANT: Never return offline.html for non-GET navigations (e.g. POST form submits),
  // otherwise a successful submit can look like "no connection" on flaky mobile networks.
  if (request.mode === 'navigate') {
    if (request.method !== 'GET') {
      return;
    }
    event.respondWith(
      (async () => {
        try {
          return await fetch(request);
        } catch (e) {
          const cache = await caches.open(CACHE_NAME);
          const cached = await cache.match(OFFLINE_URL);
          return cached || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
        }
      })()
    );
    return;
  }

  // Assets: cache-first.
  if (isAssetRequest(request, url)) {
    event.respondWith(
      (async () => {
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(request);
        if (cached) return cached;

        const response = await fetch(request);
        // Only cache successful, same-origin, basic responses.
        if (response && response.ok && response.type === 'basic') {
          cache.put(request, response.clone());
        }
        return response;
      })()
    );
  }
});

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'Famille', body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'Famille';
  const options = {
    body: data.body || '',
    icon: data.icon || '/images/logo1.png',
    badge: data.badge || '/images/logo1.png',
    data: {
      url: data.url || '/',
    },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification && event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    (async () => {
      const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

      for (const client of clientList) {
        if ('focus' in client) {
          client.focus();
          if ('navigate' in client) {
            client.navigate(targetUrl);
          }
          return;
        }
      }

      if (self.clients.openWindow) {
        await self.clients.openWindow(targetUrl);
      }
    })()
  );
});
