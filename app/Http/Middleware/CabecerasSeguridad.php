<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega cabeceras HTTP de seguridad a todas las respuestas web.
 *
 * - Content-Security-Policy: solo permite scripts propios (con nonce),
 *   estilos/fuentes propios y de fonts.bunny.net. Bloquea scripts inyectados.
 * - X-Frame-Options / frame-ancestors: impide meter el sitio en un iframe (clickjacking).
 * - X-Content-Type-Options: el navegador no "adivina" tipos de archivo.
 * - Referrer-Policy y Permissions-Policy: limitan datos y permisos del navegador.
 * - Strict-Transport-Security (HSTS): solo bajo HTTPS en producción.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->politicaContenido());
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function politicaContenido(): string
    {
        $nonce = Vite::cspNonce();
        $vite = $this->origenViteDesarrollo();
        $viteWs = $vite ? preg_replace('/^http/', 'ws', $vite) : '';

        // Alpine.js (estándar) necesita 'unsafe-eval' para evaluar sus expresiones.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' {$vite}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net {$vite}",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob:",
            "connect-src 'self' {$vite} {$viteWs}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * En desarrollo (npm run dev) los assets vienen del servidor de Vite.
     */
    private function origenViteDesarrollo(): string
    {
        if (! Vite::isRunningHot()) {
            return '';
        }

        return rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
    }
}
