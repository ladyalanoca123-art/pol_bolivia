<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use Illuminate\Http\RedirectResponse;

/**
 * Aviso "Para adoptar necesitas una cuenta": la persona elige crear cuenta o iniciar sesión y,
 * al terminar, vuelve al formulario de solicitud de esa mascota.
 *
 * El destino lo construye el servidor a partir de la mascota (nunca se recibe una URL del
 * navegador), así que no puede apuntar a otro sitio.
 */
class AccesoSolicitudAdopcionController extends Controller
{
    public function __invoke(Mascota $mascota, string $accion): RedirectResponse
    {
        abort_unless($mascota->estado === 'disponible', 404);

        redirect()->setIntendedUrl(route('solicitudes-adopcion.create', $mascota));

        return to_route($accion === 'registro' ? 'register' : 'login');
    }
}
