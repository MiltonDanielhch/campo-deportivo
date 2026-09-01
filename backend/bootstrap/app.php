<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No necesitamos EnsureFrontendRequestIsStateful porque usamos tokens Bearer,
        // no cookies de sesión SPA.

        // Para APIs: cuando auth:sanctum rechaza, devolver JSON 401
        // en lugar de intentar redirigir a una ruta 'login' inexistente.
        $middleware->redirectGuestsTo(fn () => null);

        // Alias del middleware de rol (registrado en Módulo 0.8, creado ahora)
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
