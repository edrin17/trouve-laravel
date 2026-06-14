<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hors connexion — Trouve</title>
    <meta name="theme-color" content="#3584e4">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            margin: 0; background: #f6f5f4; color: #2e3436;
        }
        header.app-bar {
            background: #3584e4; color: #fff; padding: .75rem 1.25rem;
            display: flex; align-items: center; gap: 1rem;
        }
        header.app-bar h1 { font-size: 1.1rem; margin: 0; font-weight: 600; }
        .badge-offline {
            margin-left: auto; font-size: .8rem; background: rgba(255,255,255,.2);
            border-radius: 10px; padding: .15rem .6rem;
        }
        main { padding: 1.25rem; max-width: 900px; margin: 0 auto; }
        #etat { color: #5e5c64; }
        .vide {
            background: #fff; border: 1px solid #f0a30a; border-radius: 8px;
            padding: 1.25rem; text-align: center;
        }
        #recherche {
            width: 100%; padding: .5rem .75rem; border: 1px solid #c0bfbc;
            border-radius: 6px; margin-bottom: 1rem; font-size: 1rem;
        }
        h2.maison {
            font-size: 1rem; border-bottom: 2px solid #3584e4;
            padding-bottom: .25rem; margin-top: 1.5rem;
        }
        ul.arbre { list-style: none; padding-left: 1.25rem; margin: .15rem 0; border-left: 1px dashed #d0d0d0; }
        li.noeud > .ligne {
            padding: .3rem .5rem; background: #fff; border: 1px solid #e8e8e8;
            border-radius: 6px; display: flex; align-items: center; gap: .4rem; margin: .15rem 0;
        }
        li.noeud.conflit > .ligne { border-left: 4px solid #f0a30a; background: #fff8ec; }
        .tag { background: #e8f0fe; color: #1a73e8; border-radius: 10px; padding: 0 .5rem; font-size: .75rem; }
        .badge-conflit { background: #f0a30a; color: #fff; border-radius: 10px; padding: 0 .5rem; font-size: .7rem; font-weight: 600; }
        .qte { color: #5e5c64; font-size: .85rem; }
        img.miniature { width: 28px; height: 28px; object-fit: cover; border-radius: 4px; border: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <header class="app-bar">
        <h1>Trouve — Inventaire</h1>
        <span class="badge-offline">📡 Hors connexion (lecture seule)</span>
    </header>
    <main>
        <p id="etat">Chargement de l’inventaire hors-ligne…</p>
        <input type="search" id="recherche" placeholder="Rechercher un objet, un tag…" hidden>
        <div id="contenu"></div>
    </main>

    {{-- Logique de rendu (précachée par le service worker pour fonctionner hors-ligne) --}}
    <script src="/js/offline-inventaire.js" defer></script>
</body>
</html>
