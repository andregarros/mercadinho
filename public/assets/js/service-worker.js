const CACHE_NAME = 'mercadinho-pdv-v1';
const urlsToCache = [
    '/dashboard',
    '/public/assets/css/app.css',
    '/public/assets/js/app.js',
    '/public/assets/icons/icon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache)));
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        caches.match(event.request).then((response) => response || fetch(event.request))
    );
});
