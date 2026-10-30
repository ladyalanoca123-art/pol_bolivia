<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ValidaDatosPersonales;
use App\Support\RegistroSeguridad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Validación del registro público de ciudadanos.
 * El rol (ciudadano) y el estado (activo) los asigna la base de datos: nunca se leen del formulario.
 * Los datos personales comparten reglas y mensajes con «Mi perfil» (Concerns\ValidaDatosPersonales).
 */
class RegisterRequest extends FormRequest
{
    use ValidaDatosPersonales;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarDatosPersonales();
    }

    public function rules(): array
    {
        return [
            ...$this->reglasDatosPersonales(),
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // Dos casillas separadas y obligatorias; la aceptación queda registrada (aceptaciones_legales).
            'acepta_terminos' => ['accepted'],
            'acepta_privacidad' => ['accepted'],
            // Honeypot: campo oculto que una persona nunca ve ni completa.
            'sitio_web' => ['prohibited'],
        ];
    }

    /**
     * Mensajes propios del registro, en español natural y dirigidos a la persona (tú).
     * Solo afectan a este formulario: lang/es/validation.php (login, panel) no cambia.
     */
    public function messages(): array
    {
        return [
            ...$this->mensajesDatosPersonales(),
            ...self::mensajesContrasena(),
            'acepta_terminos.accepted' => 'Para crear tu cuenta, confirma que tienes '.config('pol.legal.edad_minima').' años o más y acepta los Términos y condiciones.',
            'acepta_privacidad.accepted' => 'Para crear tu cuenta, acepta la Política de privacidad.',
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
}
