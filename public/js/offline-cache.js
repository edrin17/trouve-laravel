// Réchauffe le cache hors-ligne : tant qu'on est en ligne, on récupère
// périodiquement le snapshot de l'inventaire (/sync/pull). Le service worker
// intercepte cette requête et met sa copie en cache, de sorte que la page
// /offline puisse afficher le dernier inventaire connu en cas de coupure.
//
// Lecture seule : on ne fait qu'alimenter le cache, aucune mutation ici.
(function () {
    'use strict';

    var INTERVALLE_MS = 60000; // 1 min : suffisant pour 3 utilisateurs.

    function rechauffer() {
        if (!navigator.onLine) {
            return;
        }
        // same-origin + cookies de session : l'endpoint /sync/pull est sous auth.
        fetch('/sync/pull', { credentials: 'same-origin' }).catch(function () {
            // Hors-ligne ou erreur réseau : on réessaiera au prochain tick / event online.
        });
    }

    window.addEventListener('load', rechauffer);
    window.addEventListener('online', rechauffer);
    setInterval(rechauffer, INTERVALLE_MS);
})();
