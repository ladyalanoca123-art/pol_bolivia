<?php

namespace App\Http\Requests\Auth;

use App\Rules\TelefonoBoliviano;
use App\Support\RegistroSeguridad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validación del registro público de ciudadanos.
 * El rol (ciudadano) y el estado (activo) los asigna la base de datos: nunca se leen del formulario.
 */
class RegisterRequest extends FormRequest
{
    /** Mensaje del correo duplicado (la vista lo usa para mostrar los enlaces de acceso y recuperación). */
    public const CORREO_REGISTRADO = 'Este correo ya está registrado.';

    /** Letras Unicode (con tildes y ñ), espacios, apóstrofes, guiones y puntos; debe empezar por una letra. */
    private const PATRON_NOMBRE = "/^\\pL[\\pL\\pM '’.\\-]*$/u";

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => $this->normalizarNombre($this->input('nombres')),
            'apellidos' => $this->normalizarNombre($this->input('apellidos')),
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            'telefono' => TelefonoBoliviano::normalizar($this->input('telefono')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100', 'regex:'.self::PATRON_NOMBRE],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.self::PATRON_NOMBRE],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('usuarios', 'email')],
            'telefono' => ['bail', 'nullable', 'string', 'max:20', new TelefonoBoliviano],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // Honeypot: campo oculto que una persona nunca ve ni completa.
            'sitio_web' => ['prohibited'],
        ];
    }

    /**
     * Mensajes propios del registro, en español natural y dirigidos a la persona (tú).
     * Solo afectan a este formulario: lang/es/validation.php (login, perfil, panel) no cambia.
     */
    public function messages(): array
    {
        return [
            'nombres.required' => 'Escribe tus nombres.',
            'nombres.string' => 'Los nombres no son válidos.',
            'nombres.max' => 'Los nombres pueden tener como máximo 100 caracteres.',
            'nombres.regex' => 'Los nombres solo pueden contener letras, espacios, apóstrofes, guiones y puntos.',

            'apellidos.required' => 'Escribe tus apellidos.',
            'apellidos.string' => 'Los apellidos no son válidos.',
            'apellidos.max' => 'Los apellidos pueden tener como máximo 100 caracteres.',
            'apellidos.regex' => 'Los apellidos solo pueden contener letras, espacios, apóstrofes, guiones y puntos.',

            'email.required' => 'Escribe tu correo electrónico.',
            'email.string' => 'Escribe un correo electrónico válido, por ejemplo nombre@correo.com.',
            'email.email' => 'Escribe un correo electrónico válido, por ejemplo nombre@correo.com.',
            'email.max' => 'El correo electrónico puede tener como máximo 255 caracteres.',
            'email.unique' => self::CORREO_REGISTRADO,

            'telefono.max' => TelefonoBoliviano::MENSAJE,
            'telefono.string' => TelefonoBoliviano::MENSAJE,

            'password.required' => 'Escribe una contraseña.',
            'password.string' => 'La contraseña no es válida.',
            'password.confirmed' => 'Las contraseñas no coinciden. Escribe la misma contraseña en los dos campos.',
            // Reglas de Password::defaults() (Laravel las busca como "<campo>.password.<regla>").
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.password.mixed' => 'La contraseña debe incluir al menos una letra mayúscula y una minúscula.',
            'password.password.letters' => 'La contraseña debe incluir al menos una letra.',
            'password.password.numbers' => 'La contraseña debe incluir al menos un número.',
            'password.password.uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elige otra.',

            'sitio_web.prohibited' => 'No pudimos procesar tu registro. Inténtalo de nuevo.',
        ];
    }

    /** Si el honeypot se completó, se deja constancia en el log de seguridad (sin su contenido). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('sitio_web')) {
                app(RegistroSeguridad::class)->honeypotRegistro();
            }
        });
    }

    public function attributes(): array
    {
        return ['telefono' => 'teléfono'];
    }

    /** Quita espacios externos y colapsa los repetidos, sin alterar el resto del nombre. */
    private function normalizarNombre(mixed $valor): mixed
    {
        return is_string($valor) ? preg_replace('/\s+/u', ' ', trim($valor)) : $valor;
    }
}
