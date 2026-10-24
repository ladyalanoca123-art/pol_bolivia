<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Cambiar la fecha de entrega acordada de una solicitud aprobada. */
class FechaEntregaAcordadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_entrega_acordada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_entrega_acordada.required' => 'Indica la fecha de entrega acordada.',
            'fecha_entrega_acordada.date_format' => 'Indica una fecha válida.',
            'fecha_entrega_acordada.after_or_equal' => 'La fecha de entrega acordada no puede ser pasada.',
        ];
    }
}
