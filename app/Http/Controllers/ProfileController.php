<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\CorreoCambiadoNotification;
use App\Support\RegistroSeguridad;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const HISTORY_MESSAGE = 'No podemos eliminar tu cuenta porque tiene historial registrado (solicitudes o postulaciones) '
        .'que POL-Bolivia debe conservar. Si ya no deseas usarla, comunícate con la fundación para desactivarla.';

    /** Relaciones de App\Models\User cuyas claves foráneas impiden borrar al usuario (ON DELETE RESTRICT). */
    private const HISTORY_RELATIONS = [
        'mascotasRegistradas', 'solicitudesAdopcion', 'seguimientosAsignados', 'visitasSeguimiento',
        'incidenciasReportadas', 'incidenciasAtendidas', 'postulacionesVoluntariado', 'postulacionesRevisadas',
        'perfilVoluntario',
    ];

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * Si el correo cambia (ProfileUpdateRequest ya exigió la contraseña actual): como en Breeze, el correo nuevo
     * queda sin verificar (email_verified_at = null), se avisa al correo ANTERIOR y queda en el log "seguridad".
     */
    public function update(ProfileUpdateRequest $request, RegistroSeguridad $registro): RedirectResponse
    {
        $user = $request->user();
        $datos = $request->validated();

        // Solo cambian mayúsculas y minúsculas: se conserva el correo guardado (no es un cambio de correo).
        if (! $request->cambiaElCorreo()) {
            unset($datos['email']);
        }

        $correoAnterior = $user->email;
        $user->fill($datos);
        $cambioElCorreo = $user->isDirty('email');

        if ($cambioElCorreo) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($cambioElCorreo) {
            $registro->correoCambiado($user, $correoAnterior, $user->email);
            $correoNuevo = $user->email;
            // Después de responder: un fallo del servidor de correo no deshace el cambio.
            defer(fn () => Notification::route('mail', $correoAnterior)
                ->notify(new CorreoCambiadoNotification($user->nombres, $correoNuevo)));
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     *
     * Solo un ciudadano activo (UserPolicy::deleteOwnAccount) y solo si no tiene historial: PostgreSQL protege
     * esos registros (ON DELETE RESTRICT) y POL-Bolivia debe conservarlos. La sesión se cierra solo si se eliminó.
     * Cada intento queda en el log "seguridad" con su resultado (App\Support\RegistroSeguridad), sin la contraseña.
     */
    public function destroy(Request $request, RegistroSeguridad $registro): RedirectResponse
    {
        $user = $request->user();

        $authorization = Gate::inspect('deleteOwnAccount', $user);

        if ($authorization->denied()) {
            $registro->eliminacionCuenta($user, 'bloqueada_por_rol');
        }

        $authorization->authorize();

        try {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        } catch (ValidationException $exception) {
            $registro->eliminacionCuenta($user, 'contrasena_incorrecta');

            throw $exception;
        }

        if ($this->hasHistory($user)) {
            $registro->eliminacionCuenta($user, 'bloqueada_por_historial');

            return $this->historyPreserved();
        }

        try {
            DB::transaction(fn () => $user->delete());
        } catch (QueryException $exception) {
            // Respaldo: un registro relacionado que hasHistory() no conoce (SQLSTATE 23503, clave foránea).
            // Cualquier otro error sigue el manejo normal.
            if ($exception->getCode() !== '23503') {
                throw $exception;
            }

            $registro->eliminacionCuenta($user, 'bloqueada_por_historial');

            return $this->historyPreserved();
        }

        $registro->eliminacionCuenta($user, 'eliminada');

        // Auth::logout() renueva el token «Recordarme» guardando el modelo: sobre un usuario ya eliminado,
        // save() lo volvería a insertar. Sin token no guarda nada y sigue registrando el cierre de sesión.
        $user->setRememberToken(null);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /** Si el usuario tiene registros que le hacen referencia con ON DELETE RESTRICT (las 9 claves foráneas hacia usuarios). */
    private function hasHistory(User $user): bool
    {
        return collect(self::HISTORY_RELATIONS)->contains(fn (string $relation) => $user->{$relation}()->exists());
    }

    private function historyPreserved(): RedirectResponse
    {
        return Redirect::route('profile.edit')->withErrors(['cuenta' => self::HISTORY_MESSAGE], 'userDeletion');
    }
}
