<?php

namespace App\Http\Requests\Concerns;

use App\Rules\TelefonoBoliviano;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Datos personales de una cuenta (nombres, apellidos, correo y teléfono) con las MISMAS reglas, normalización y
 * mensajes en el registro (Auth\RegisterRequest) y en «Mi perfil» (ProfileUpdateRequest), para que no se separen.
 * También los mensajes de la política de contraseñas (Password::defaults()).
 */
trait ValidaDatosPersonales
{
    /** Mensaje del correo duplicado (la vista del registro lo usa para mostrar los enlaces de acceso y recuperación). */
    public const CORREO_REGISTRADO = 'Este correo ya está registrado.';

    /** Letras Unicode (con tildes y ñ), espacios, apóstrofes, guiones y puntos; debe empezar por una letra. */
    private const PATRON_NOMBRE = "/^\\pL[\\pL\\pM '’.\\-]*$/u";

    /** Quita espacios sobrantes de los nombres, pasa el correo a minúsculas y normaliza el teléfono. */
    protected function normalizarDatosPersonales(): void
    {
        $this->merge([
            'nombres' => $this->normalizarNombre($this->input('nombres')),
            'apellidos' => $this->normalizarNombre($this->input('apellidos')),
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            'telefono' => TelefonoBoliviano::normalizar($this->input('telefono')),
        ]);
    }

    /** $ignorarId: la propia cuenta, cuyo correo no cuenta como duplicado. */
    protected function reglasDatosPersonales(?int $ignorarId = null): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100', 'regex:'.self::PATRON_NOMBRE],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.self::PATRON_NOMBRE],
            // usuarios.email es CITEXT: la base compara sin distinguir mayúsculas y minúsculas, así que unique también.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($ignorarId)],
            'telefono' => ['bail', 'nullable', 'string', 'max:20', new TelefonoBoliviano],
        ];
    }

    protected function mensajesDatosPersonales(): array
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
        ];
    }

    /** Mensajes de Password::defaults() (Laravel los busca como "<campo>.password.<regla>") y de la confirmación. */
    public static function mensajesContrasena(string $campo = 'password'): array
    {
        return [
            "{$campo}.required" => 'Escribe una contraseña.',
            "{$campo}.string" => 'La contraseña no es válida.',
            "{$campo}.confirmed" => 'Las contraseñas no coinciden. Escribe la misma contraseña en los dos campos.',
            "{$campo}.min" => 'La contraseña debe tener al menos 8 caracteres.',
            "{$campo}.password.mixed" => 'La contraseña debe incluir al menos una letra mayúscula y una minúscula.',
            "{$campo}.password.letters" => 'La contraseña debe incluir al menos una letra.',
            "{$campo}.password.numbers" => 'La contraseña debe incluir al menos un número.',
            "{$campo}.password.uncompromised" => 'Esta contraseña apareció en una filtración de datos. Elige otra.',
        ];
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
