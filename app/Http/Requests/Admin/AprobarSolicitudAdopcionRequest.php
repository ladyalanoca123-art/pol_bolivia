<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Aprobar: fecha de entrega acordada y comentario, ambos opcionales. */
class AprobarSolicitudAdopcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_entrega_acordada' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'comentarios_admin' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_entrega_acordada.date_format' => 'Indica una fecha válida.',
            'fecha_entrega_acordada.after_or_equal' => 'La fecha de entrega acordada no puede ser pasada.',
            'comentarios_admin.max' => 'El comentario puede tener como máximo 1000 caracteres.',
        ];
    }
}
