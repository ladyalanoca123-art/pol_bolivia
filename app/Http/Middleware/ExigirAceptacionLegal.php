<?php

namespace App\Http\Middleware;

use App\Support\AceptacionDocumentosLegales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si la persona con sesión no aceptó los Términos y la Política de privacidad vigentes (cuentas creadas antes
 * de existir, o una versión nueva), la lleva a aceptarlos antes de entrar a su cuenta. Las páginas públicas
 * (inicio, catálogo y ficha de mascotas), las legales y cerrar sesión siempre quedan libres.
 */
class ExigirAceptacionLegal
{
    private const RUTAS_LIBRES = ['home', 'mascotas.catalogo', 'mascotas.public.show', 'legal.*', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $request->routeIs(...self::RUTAS_LIBRES) && AceptacionDocumentosLegales::pendiente($usuario)) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('legal.aceptar');
        }

        return $next($request);
    }
}
