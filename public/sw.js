// Service worker de Trouve — cache de l'app (shell) ET, depuis v2, un snapshot
// de l'inventaire (réponse de /sync/pull) pour la consultation HORS-LIGNE en
// lecture seule. Les mutations restent en ligne (Livewire exige le réseau).
// Incrémenter CACHE à chaque modification pour purger l'ancien cache.
const CACHE = 'trouve-v2';

// Endpoint dont la réponse JSON est cachée pour la consultation hors-ligne.
const SNAPSHOT = '/sync/pull';

// Ressources mises en cache dès l'installation (le « shell » minimal).
const PRECACHE = [
    '/offline',
    '/js/offline-inventaire.js',
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

    // Snapshot de l'inventaire : réseau d'abord (et on met à jour le cache pour
    // la prochaine coupure), repli sur la dernière réponse cachée hors-ligne.
    // Si rien en cache (jamais pullé / cache purgé), l'échec se propage : la
    // page /offline le détecte et invite à se reconnecter.
    if (url.pathname === SNAPSHOT) {
        event.respondWith(
            fetch(request)
                .then((reponse) => {
                    if (reponse.ok) {
                        const copie = reponse.clone();
                        caches.open(CACHE).then((cache) => cache.put(SNAPSHOT, copie));
                    }
                    return reponse;
                })
                .catch(() => caches.match(SNAPSHOT))
        );
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
