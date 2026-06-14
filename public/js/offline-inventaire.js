// Consultation hors-ligne de l'inventaire (LECTURE SEULE).
//
// Lit le dernier snapshot de /sync/pull (servi par le service worker depuis son
// cache quand le réseau est absent), reconstruit l'arbre maison → items et offre
// une recherche locale. Aucune mutation : les modifications restent en ligne.
//
// Trois états :
//   - données disponibles → arbre + recherche ;
//   - cache vide/purgé hors-ligne → message + réessai auto au retour du réseau ;
//   - en ligne → fetch frais (le SW met le cache à jour pour la prochaine fois).
(function () {
    'use strict';

    var etat = document.getElementById('etat');
    var champ = document.getElementById('recherche');
    var contenu = document.getElementById('contenu');

    var donnees = null; // { houses: [...], items: [...], curseur }

    function echapper(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function formatQuantite(q, unite) {
        if (q == null) {
            return '';
        }
        // retire les zéros décimaux inutiles (12.00 → 12, 1.50 → 1.5)
        var n = String(parseFloat(q));
        return '×' + n + (unite ? ' ' + echapper(unite) : '');
    }

    // Construit l'arbre : map parent_id → enfants, racines = parent_id null.
    function indexer(items) {
        var parEnfants = {};
        items.forEach(function (it) {
            var cle = it.parent_id == null ? 'root' : it.parent_id;
            (parEnfants[cle] = parEnfants[cle] || []).push(it);
        });
        return parEnfants;
    }

    function trier(liste) {
        return liste.slice().sort(function (a, b) {
            // conteneurs d'abord, puis par nom
            if (!!a.is_container !== !!b.is_container) {
                return a.is_container ? -1 : 1;
            }
            return String(a.name).localeCompare(String(b.name));
        });
    }

    function rendreNoeud(item, parEnfants) {
        var li = document.createElement('li');
        li.className = 'noeud' + (item.en_conflit ? ' conflit' : '');

        var ligne = document.createElement('div');
        ligne.className = 'ligne';

        var html = '<span>' + (item.is_container ? '📦' : '•') + '</span>';
        if (item.image_filename) {
            html += '<img class="miniature" alt="" src="/storage/items/' + echapper(item.image_filename) + '">';
        }
        html += '<span style="font-weight:' + (item.is_container ? '600' : '400') + '">' + echapper(item.name) + '</span>';
        if (item.en_conflit) {
            html += '<span class="badge-conflit">⚠ conflit</span>';
        }
        var q = formatQuantite(item.quantity, item.unit);
        if (q) {
            html += '<span class="qte">' + q + '</span>';
        }
        (item.tags || []).forEach(function (t) {
            html += '<span class="tag">' + echapper(t.name) + '</span>';
        });
        ligne.innerHTML = html;
        li.appendChild(ligne);

        var enfants = parEnfants[item.id];
        if (enfants && enfants.length) {
            var ul = document.createElement('ul');
            ul.className = 'arbre';
            trier(enfants).forEach(function (e) { ul.appendChild(rendreNoeud(e, parEnfants)); });
            li.appendChild(ul);
        }
        return li;
    }

    // Filtre les items qui matchent le terme (nom / description / tag), insensible
    // à la casse. Renvoie l'ensemble des items à garder + leurs ancêtres (pour ne
    // pas casser l'arbre).
    function filtrer(items, terme) {
        terme = terme.trim().toLowerCase();
        if (terme === '') {
            return items;
        }
        var parId = {};
        items.forEach(function (it) { parId[it.id] = it; });

        var garder = {};
        items.forEach(function (it) {
            var dansTags = (it.tags || []).some(function (t) {
                return String(t.name).toLowerCase().indexOf(terme) !== -1;
            });
            var match = String(it.name || '').toLowerCase().indexOf(terme) !== -1
                || String(it.description || '').toLowerCase().indexOf(terme) !== -1
                || dansTags;
            if (match) {
                // garder l'item et toute sa lignée d'ancêtres
                var cur = it;
                while (cur && !garder[cur.id]) {
                    garder[cur.id] = true;
                    cur = cur.parent_id == null ? null : parId[cur.parent_id];
                }
            }
        });
        return items.filter(function (it) { return garder[it.id]; });
    }

    function rendre() {
        var terme = champ.value || '';
        var items = filtrer(donnees.items || [], terme);
        var parEnfants = indexer(items);
        contenu.innerHTML = '';

        var maisons = (donnees.houses || []).slice().sort(function (a, b) {
            return String(a.name).localeCompare(String(b.name));
        });

        var aAffiche = false;
        maisons.forEach(function (maison) {
            var racines = (parEnfants.root || []).filter(function (it) {
                return it.house_id === maison.id;
            });
            if (terme.trim() !== '' && racines.length === 0) {
                return; // en recherche, on masque les maisons vides
            }
            aAffiche = true;
            var h2 = document.createElement('h2');
            h2.className = 'maison';
            h2.innerHTML = '🏠 ' + echapper(maison.name);
            contenu.appendChild(h2);

            var ul = document.createElement('ul');
            ul.className = 'arbre';
            trier(racines).forEach(function (it) { ul.appendChild(rendreNoeud(it, parEnfants)); });
            contenu.appendChild(ul);
        });

        if (!aAffiche) {
            contenu.innerHTML = '<p id="etat">Aucun résultat.</p>';
        }
    }

    function afficherVide() {
        champ.hidden = true;
        etat.style.display = 'none';
        contenu.innerHTML =
            '<div class="vide">' +
            '<p><strong>Aucune donnée hors-ligne disponible.</strong></p>' +
            '<p>L’inventaire n’a pas encore été mis en cache (ou le cache a été vidé). ' +
            'Reconnectez-vous : il se chargera automatiquement.</p>' +
            '</div>';
    }

    function afficherDonnees(data) {
        donnees = data;
        etat.style.display = 'none';
        champ.hidden = false;
        champ.addEventListener('input', rendre);
        rendre();
    }

    function charger() {
        return fetch('/sync/pull', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r || !r.ok) {
                    throw new Error('indisponible');
                }
                return r.json();
            })
            .then(afficherDonnees)
            .catch(function () {
                // Pas de réseau (ou réponse inexploitable) ET rien en cache.
                afficherVide();
            });
    }

    // Repli robuste : tant qu'on n'a PAS de données, on retente périodiquement.
    // On ne se fie pas seulement à l'event `online` ni à navigator.onLine, peu
    // fiables selon les navigateurs (cf. Firefox : onLine=false mais fetch OK).
    // Dès qu'un essai réussit (réseau revenu ou cache à nouveau peuplé), la page
    // se remplit et on arrête de retenter.
    var REESSAI_MS = 10000;
    var minuterie = setInterval(function () {
        if (donnees) {
            clearInterval(minuterie);
            return;
        }
        charger();
    }, REESSAI_MS);

    // Coup de pouce immédiat au retour explicite du réseau (quand l'event existe).
    window.addEventListener('online', function () {
        if (!donnees) {
            charger();
        }
    });

    charger();
})();
