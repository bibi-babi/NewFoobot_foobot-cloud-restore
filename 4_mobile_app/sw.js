/**
 * NewFoobot Air Quality - Service Worker
 * Gestione caching PWA offline e notifiche Push di sistema
 */

const CACHE_NAME = 'newfoobot-v1.0.0';
const STATIC_ASSETS = [
    './',
    './index.html',
    './manifest.json',
    './assets/css/app.css?v=1.0',
    './assets/js/vendor/chart.min.js?v=1.0',
    './assets/js/app.js?v=1.0',
    './assets/icons/icon.svg?v=1.0',
    './assets/icons/icon-192.png?v=1.0',
    './assets/icons/icon-512.png',
    './assets/icons/icon-maskable-512.png',
    './languages/it.json',
    './languages/en.json',
    './languages/fr.json',
    './languages/de.json',
    './languages/es.json',
    './languages/template.json'
];

// Installazione: pre-cache degli asset dell'app shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Attivazione: rimozione vecchie versioni della cache
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Intercettazione richieste di rete
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Le chiamate API viaggiano sempre in rete (network-only o fallback json)
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(JSON.stringify({ 
                    success: false, 
                    error: 'offline', 
                    message: 'Dispositivo offline: impossibile contattare il server Foobot' 
                }), {
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }

    // Per le richieste di navigazione HTML (anche con ?ticket=...): Network-First con fallback a index.html in cache
    if (event.request.mode === 'navigate' || event.request.destination === 'document') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const match = await caches.match('./index.html', { ignoreSearch: true });
                if (match) return match;
                const matchRoot = await caches.match('./');
                return matchRoot || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
            })
        );
        return;
    }

    // Per gli asset statici: Cache First con fallback di rete protetto da .catch()
    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                // Aggiornamento in background della cache (stale-while-revalidate)
                fetch(event.request).then((networkRes) => {
                    if (networkRes && networkRes.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkRes));
                    }
                }).catch(() => {});
                return cached;
            }
            return fetch(event.request).catch(async () => {
                const match = await caches.match(event.request, { ignoreSearch: true });
                if (match) return match;
                return new Response('', { status: 408, statusText: 'Request timed out' });
            });
        })
    );
});

// Ricezione Notifiche Push
self.addEventListener('push', (event) => {
    let data = {
        title: 'NewFoobot Air Quality',
        body: 'Aggiornamento qualità dell\'aria rilevato',
        icon: './assets/icons/icon-192.png',
        badge: './assets/icons/icon-192.png',
        tag: 'foobot-air-alert',
        renotify: true,
        data: { url: './index.html' }
    };

    if (event.data) {
        try {
            const payload = event.data.json();
            data = Object.assign(data, payload);
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon,
            badge: data.badge,
            tag: data.tag,
            renotify: data.renotify,
            vibrate: [200, 100, 200],
            data: data.data
        })
    );
});

// Click sulla notifica: apre o mette a fuoco la finestra dell'App
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : './index.html';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url.includes('index.html') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
