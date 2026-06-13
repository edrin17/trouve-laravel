<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hors connexion — Trouve</title>
    <meta name="theme-color" content="#3584e4">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: #f6f5f4; color: #2e3436; text-align: center;
        }
        .carte {
            background: #fff; border: 1px solid #e0dedb; border-radius: 10px;
            padding: 2rem; max-width: 360px; box-shadow: 0 10px 40px rgba(0,0,0,.08);
        }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { color: #5e5c64; line-height: 1.5; }
        button {
            margin-top: 1rem; padding: .55rem 1.2rem; border: none;
            background: #3584e4; color: #fff; border-radius: 6px;
            cursor: pointer; font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="carte">
        <h1>📡 Hors connexion</h1>
        <p>Trouve n’a pas pu joindre le serveur. Vérifiez votre connexion, puis réessayez.</p>
        <button type="button" onclick="location.reload()">Réessayer</button>
    </div>
</body>
</html>
