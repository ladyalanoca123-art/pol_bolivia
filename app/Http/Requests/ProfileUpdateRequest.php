<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaDatosPersonales;
use Illuminate\Foundation\Http\FormRequest;

/**
 * «Mi perfil»: nombres, apellidos, correo y teléfono con las mismas reglas y mensajes que el registro
 * (Concerns\ValidaDatosPersonales). Cambiar el correo exige la contraseña actual; cambiar solo el nombre o
 * el teléfono, no. Un cambio solo de mayúsculas en el correo no cuenta como cambio.
 */
class ProfileUpdateRequest extends FormRequest
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
            ...$this->reglasDatosPersonales(ignorarId: $this->user()->id),
            'current_password' => $this->cambiaElCorreo() ? ['required', 'string', 'current_password'] : ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->mensajesDatosPersonales(),
            'current_password.required' => 'Para cambiar tu correo escribe tu contraseña actual.',
            'current_password.string' => 'La contraseña actual no es correcta.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }

    /** Si el correo enviado es distinto del actual (sin importar mayúsculas y minúsculas). */
    public function cambiaElCorreo(): bool
    {
        $correo = $this->input('email');

        return is_string($correo) && mb_strtolower($correo) !== mb_strtolower($this->user()->email);
    }
}
