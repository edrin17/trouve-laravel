// Service worker de Trouve — cache de l'app (PAS des données).
// Incrémenter CACHE à chaque modification pour purger l'ancien cache.
const CACHE = 'trouve-v1';

// Ressources mises en cache dès l'installation (le « shell » minimal).
const PRECACHE = [
    '/offline',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE))
    );
    self.skipWaiting();
});

// À l'activation : supprimer les anciens caches (versions précédentes).
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cles) =>
            Promise.all(cles.filter((c) => c !== CACHE).map((c) => caches.delete(c)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // On ne touche qu'aux GET de même origine.
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Ne jamais mettre en cache Livewire (réactivité) ni le service worker lui-même.
    if (url.pathname.startsWith('/livewire') || url.pathname === '/sw.js') {
        return;
    }

    // Navigations (pages HTML) : réseau d'abord, repli sur le cache puis /offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((reponse) => {
                    const copie = reponse.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copie));
                    return reponse;
                })
                .catch(() => caches.match(request).then((c) => c || caches.match('/offline')))
        );
        return;
    }

    // Autres GET (icônes, manifeste, /storage…) : cache d'abord, sinon réseau.
    event.respondWith(
        caches.match(request).then((cache) =>
            cache ||
            fetch(request).then((reponse) => {
                // Ne cacher que les réponses valides same-origin.
                if (reponse.ok && reponse.type === 'basic') {
                    const copie = reponse.clone();
                    caches.open(CACHE).then((c) => c.put(request, copie));
                }
                return reponse;
            })
        )
    );
});
