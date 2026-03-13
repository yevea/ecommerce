/**
 * Service Worker for the "Añadir Tablón" PWA.
 * Provides basic caching of the app shell for faster repeat loads.
 */
var CACHE_NAME = 'tablon-pwa-v1';
var SHELL_URLS = [
    '/AddTablon'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(SHELL_URLS);
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
    // Only cache GET requests; let POSTs (form submissions) go to network
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request).then(function (response) {
            // Cache successful responses
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
