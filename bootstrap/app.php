<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Endpoints de synchro = API JSON same-origin consommée par le client offline.
        // On garde l'auth par session mais on exclut ces routes du CSRF web.
        $middleware->validateCsrfTokens(except: ['sync/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Rendre les erreurs (validation 422, auth 401) en JSON pour l'API de synchro.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('sync/*'),
        );
    })->create();
