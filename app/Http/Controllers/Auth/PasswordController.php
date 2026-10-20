<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Notifications\ContrasenaCambiadaNotification;
use App\Support\RegistroSeguridad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * Misma política y mensajes que el registro. Después del cambio: se cierran las sesiones de los OTROS
     * dispositivos (la actual sigue), se renueva el «Recordarme», se avisa por correo y queda en el log "seguridad".
     */
    public function update(Request $request, RegistroSeguridad $registro): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            ...RegisterRequest::mensajesContrasena(),
            'current_password.required' => 'Escribe tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            // Anula el «Recordarme» de todos los dispositivos (este sigue con su sesión iniciada).
            'remember_token' => Str::random(60),
        ])->save();

        // Cierra las sesiones abiertas en otros dispositivos (vía AuthenticateSession) y, con sesiones en la base
        // (SESSION_DRIVER=database), las borra directamente salvo la actual.
        Auth::logoutOtherDevices($validated['password']);

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $registro->contrasenaCambiadaPerfil($user);
        // Después de responder: un fallo del servidor de correo no deshace el cambio.
        defer(fn () => $user->notify(new ContrasenaCambiadaNotification(desdePerfil: true)));

        return back()->with('status', 'password-updated');
    }
}
