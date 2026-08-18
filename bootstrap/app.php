<?php

use App\Http\Middleware\SetPermissionsTeamId;
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
        $middleware->statefulApi();
        $middleware->alias(['permissions.team' => SetPermissionsTeamId::class]);

        // This is an API-only app with no 'login' route. Without this, the default
        // Authenticate middleware tries to build one for any guest request that
        // doesn't explicitly ask for JSON (e.g. a bare browser hit), and throws
        // a 500 (RouteNotFoundException) instead of a clean 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
