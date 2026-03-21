/**
 * Service Worker for the "Añadir Tablón" PWA.
 * Caches the complete app shell so the page loads fully offline.
 * POST submissions are handled client-side via IndexedDB queue.
 *
 * The variable BASE is injected by the PHP controller (AddTablon?action=sw)
 * so that all paths resolve correctly regardless of the FacturaScripts
 * installation directory (e.g. "/" or "/facturascripts/").
 */
var CACHE_NAME = 'tablon-pwa-v8';
var APP_SHELL = BASE + 'AddTablon';
var SHELL_URLS = [
    APP_SHELL,
    BASE + 'Plugins/ecommerce/Assets/JS/AddTablon.js',
    BASE + 'AddTablon?action=icon&size=192',
    BASE + 'AddTablon?action=icon&size=512',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/webfonts/fa-solid-900.woff2'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(SHELL_URLS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (n) { return n !== CACHE_NAME; })
                    .map(function (n) { return caches.delete(n); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    // Let POSTs go to network — offline queue is handled client-side
    if (event.request.method !== 'GET') {
        return;
    }

    // Navigation requests (HTML pages): network-first, fall back to cached app shell
    if (event.request.mode === 'navigate') {
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
                return caches.match(event.request).then(function (cached) {
                    return cached || caches.match(APP_SHELL);
                });
            })
        );
        return;
    }

    // Static assets (JS, CSS, fonts, images): cache-first, fall back to network
    event.respondWith(
        caches.match(event.request).then(function (cached) {
            if (cached) {
                return cached;
            }
            return fetch(event.request).then(function (response) {
                if (response.ok) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            });
        })
    );
});
