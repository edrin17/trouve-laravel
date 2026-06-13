<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        <style>
            * { box-sizing: border-box; }
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
    </body>
</html>
