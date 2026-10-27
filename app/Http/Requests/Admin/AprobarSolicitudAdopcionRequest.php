<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprobar (reservar la mascota) es un solo clic. El comentario es opcional: si se escribe, queda en el historial
 * junto con quién aprobó y cuándo, para que los demás administradores sepan por qué se aprobó.
 */
class AprobarSolicitudAdopcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comentarios_admin' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comentarios_admin.string' => 'El comentario no es válido.',
            'comentarios_admin.max' => 'El comentario puede tener como máximo 1000 caracteres.',
        ];
    }
}
