<?php

namespace App\Http\Controllers;

use App\Support\AceptacionDocumentosLegales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Páginas públicas de Términos y condiciones, Política de privacidad y Política de cookies, y la pantalla
 * donde una cuenta existente acepta la versión vigente de los Términos y la Política de privacidad.
 */
class DocumentoLegalController extends Controller
{
    public function terminos(): View
    {
        return view('legal.terminos');
    }

    public function privacidad(): View
    {
        return view('legal.privacidad');
    }

    public function cookies(): View
    {
        return view('legal.cookies');
    }

    public function aceptar(Request $request): View|RedirectResponse
    {
        if (! AceptacionDocumentosLegales::pendiente($request->user())) {
            return redirect()->route('dashboard');
        }

        return view('legal.aceptar');
    }

    public function guardarAceptacion(Request $request): RedirectResponse
    {
        $request->validate([
            'acepta_terminos' => ['accepted'],
            'acepta_privacidad' => ['accepted'],
        ], [
            'acepta_terminos.accepted' => 'Para continuar, acepta los Términos y condiciones.',
            'acepta_privacidad.accepted' => 'Para continuar, acepta la Política de privacidad.',
        ]);

        AceptacionDocumentosLegales::registrar($request->user(), $request, 'actualizacion');

        return redirect()->intended(route('dashboard'));
    }
}
