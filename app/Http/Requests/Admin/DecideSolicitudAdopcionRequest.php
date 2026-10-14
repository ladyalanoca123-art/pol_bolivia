<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Toda decisión del administrador sobre una solicitud de adopción exige un motivo.
 * El motivo se guarda en comentarios_admin y la auditoría conserva su historial.
 */
class DecideSolicitudAdopcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comentarios_admin' => ['required', 'string', 'min:5', 'max:1000', 'not_regex:/^\s*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'comentarios_admin.required' => 'Escribe el motivo de la decisión.',
            'comentarios_admin.min' => 'El motivo debe tener al menos 5 caracteres.',
            'comentarios_admin.not_regex' => 'Escribe el motivo de la decisión.',
        ];
    }
}
