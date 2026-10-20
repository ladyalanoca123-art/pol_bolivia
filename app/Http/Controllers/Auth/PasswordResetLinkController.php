<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RegistroSeguridad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * La respuesta es SIEMPRE la misma (passwords.sent), exista o no la cuenta, esté desactivada o se haya
     * alcanzado el límite de envíos: no revela qué correos están registrados. Para que el tiempo de respuesta
     * tampoco lo delate, todo el trabajo (buscar la cuenta, crear el token y enviar el correo) ocurre en defer(),
     * después de enviar la respuesta: la petición hace lo mismo en todos los casos (validar y responder).
     *
     * @throws ValidationException
     */
    public function store(Request $request, RegistroSeguridad $registro): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $correo = mb_strtolower(trim((string) $request->input('email')));
        defer(fn () => $this->enviarEnlace($correo, $registro));

        return back()->with('status', __('passwords.sent'));
    }

    /** Envía el enlace solo a una cuenta existente y activa, y deja constancia del resultado (sin el token). */
    private function enviarEnlace(string $correo, RegistroSeguridad $registro): void
    {
        $usuario = User::query()->whereRaw('lower(email) = ?', [$correo])->first();

        if ($usuario === null) {
            $registro->recuperacionSolicitada(null, $correo, 'correo_inexistente');

            return;
        }

        if (! $usuario->estado) {
            $registro->recuperacionSolicitada($usuario, null, 'cuenta_desactivada');

            return;
        }

        // Laravel limita a un enlace por cuenta cada auth.passwords.*.throttle segundos (RESET_THROTTLED).
        $estado = Password::sendResetLink(['email' => $usuario->email]);

        $registro->recuperacionSolicitada($usuario, null, $estado === Password::RESET_LINK_SENT ? 'enviado' : 'limite');
    }
}
