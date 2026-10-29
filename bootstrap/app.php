<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ExpulsarCuentaDesactivada;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Seguridad aplicada a todas las rutas web.
        // AuthenticateSession: si la contraseña cambia (o se usa logoutOtherDevices), las sesiones
        // abiertas con la contraseña anterior se cierran en su siguiente petición.
        // ExpulsarCuentaDesactivada: una cuenta desactivada pierde su sesión y su «Recordarme».
        $middleware->web(append: [
            AuthenticateSession::class,
            CabecerasSeguridad::class,
            'throttle:general',
            ExpulsarCuentaDesactivada::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
