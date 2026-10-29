<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seguimiento = $this->route('seguimiento');

        return auth()->user()?->can('work', $seguimiento) ?? false;
    }

    public function rules(): array
    {
        return [
            // Se programa el día acordado con la familia: hoy o más adelante.
            'fecha_programada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'observaciones' => ['nullable', 'string'],
            'estado_salud' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_programada.required' => 'Indica el día acordado con la familia.',
            'fecha_programada.date_format' => 'Indica una fecha válida.',
            'fecha_programada.after_or_equal' => 'La fecha de la visita no puede ser pasada.',
        ];
    }
}
