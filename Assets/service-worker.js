/**
 * Service Worker for the "Añadir Tablón" PWA.
 * Caches the app shell so the page loads fully offline.
 * POST submissions are handled client-side via IndexedDB queue.
 */
var CACHE_NAME = 'tablon-pwa-v3';
var SHELL_URLS = [
    '/AddTablon',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/webfonts/fa-solid-900.woff2'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(SHELL_URLS).catch(function () {
                // If CDN URLs fail (e.g. during build), cache what we can
                return cache.add('/AddTablon');
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (n) { return n !== CACHE_NAME; })
                    .map(function (n) { return caches.delete(n); })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    // Let POSTs go to network — offline queue is handled client-side
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request).then(function (response) {
            if (response.ok) {
                var clone = response.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(event.request, clone);
                });
            }
            return response;
        }).catch(function () {
            return caches.match(event.request);
        })
    );
});
