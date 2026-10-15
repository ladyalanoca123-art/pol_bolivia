<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de una cuenta desactivada (usuarios.estado = false) en su siguiente petición.
 *
 * LoginRequest ya impide iniciar sesión con una cuenta desactivada, pero no cierra una sesión abierta
 * antes de la desactivación ni impide volver a entrar con la cookie de «Recordarme»: el guard la acepta
 * sin mirar el estado. Este middleware cubre ambos casos en todas las rutas web.
 *
 * Auth::logout() olvida la cookie de «Recordarme» y renueva el remember_token solo de esta cuenta, así
 * que sus demás sesiones y cookies dejan de servir; las otras cuentas no se ven afectadas.
 * Se responde 403, igual que EnsureUserHasRole con una cuenta inactiva.
 */
class ExpulsarCuentaDesactivada
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->estado) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, __('auth.inactive'));
        }

        return $next($request);
    }
}
