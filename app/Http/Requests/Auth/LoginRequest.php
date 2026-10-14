<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el correo antes de validar: sin espacios al inicio o al final y en minúsculas.
     * La contraseña no se modifica.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        // Sin reglas de complejidad: eso solo aplica al crear o cambiar la contraseña.
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // El callback solo se ejecuta si el correo y la contraseña ya son correctos, así que el
        // estado de la cuenta nunca se revela a quien no conoce la contraseña. Si devuelve false,
        // Laravel no inicia la sesión (y dispara el evento Failed como en cualquier rechazo).
        $cuentaDesactivada = false;

        if (! Auth::attemptWhen(
            $this->only('email', 'password'),
            function ($usuario) use (&$cuentaDesactivada): bool {
                $cuentaDesactivada = ! $usuario->estado;

                return ! $cuentaDesactivada;
            },
            $this->boolean('remember')
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans($cuentaDesactivada ? 'auth.inactive' : 'auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')).'|'.$this->ip()
        );
    }
}
