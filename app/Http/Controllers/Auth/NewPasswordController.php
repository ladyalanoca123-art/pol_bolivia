<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\ContrasenaCambiadaNotification;
use App\Support\RegistroSeguridad;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * La nueva contraseña cumple la misma política que el registro (y con sus mensajes). Un enlace inválido o
     * vencido, o de una cuenta inexistente o desactivada, recibe el mismo mensaje genérico. Tras el cambio se
     * renueva el «Recordarme», se cierran todas las sesiones de la cuenta y se le avisa por correo.
     *
     * @throws ValidationException
     */
    public function store(Request $request, RegistroSeguridad $registro): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], (new RegisterRequest)->messages());

        // El token va en un campo oculto: si falta, el mismo mensaje genérico que un enlace inválido (en "email",
        // que es el error que muestra la pantalla).
        $token = $request->input('token');
        $usuario = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $request->input('email')))])->first();

        if (! is_string($token) || $token === '' || $usuario === null || ! $usuario->estado) {
            return $this->enlaceInvalido($request);
        }

        $status = Password::reset(
            ['email' => $usuario->email, ...$request->only('password', 'password_confirmation', 'token')],
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Sesiones guardadas en la base (SESSION_DRIVER=database): se cierran todas las de la cuenta.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->enlaceInvalido($request);
        }

        $registro->contrasenaRestablecida($usuario);
        // Después de responder: un fallo del servidor de correo no impide el cambio ya hecho.
        defer(fn () => $usuario->notify(new ContrasenaCambiadaNotification));

        return redirect()->route('login')->with('status', __('passwords.reset'));
    }

    private function enlaceInvalido(Request $request): RedirectResponse
    {
        return back()->withInput($request->only('email'))->withErrors(['email' => __('passwords.token')]);
    }
}
