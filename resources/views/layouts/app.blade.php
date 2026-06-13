<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        {{-- PWA --}}
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#3584e4">
        <link rel="icon" type="image/png" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        <style>
            * { box-sizing: border-box; }
            [x-cloak] { display: none !important; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 0;
                background: #f6f5f4;
                color: #2e3436;
            }
            header.app-bar {
                background: #3584e4;
                color: #fff;
                padding: 0.75rem 1.25rem;
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            header.app-bar h1 { font-size: 1.1rem; margin: 0; font-weight: 600; }
            main { padding: 1.25rem; max-width: 900px; margin: 0 auto; }
        </style>

        @livewireStyles
    </head>
    <body>
        {{ $slot }}

        @livewireScripts

        {{-- Enregistrement du service worker (PWA) --}}
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js').catch(() => {});
                });
            }
        </script>
    </body>
</html>
