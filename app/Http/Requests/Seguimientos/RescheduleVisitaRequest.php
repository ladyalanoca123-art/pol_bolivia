<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

/** Reprogramar una visita: el nuevo día acordado con la familia (hoy o más adelante). */
class RescheduleVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('work', $this->route('seguimiento')) ?? false;
    }

    public function rules(): array
    {
        return ['fecha_programada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']];
    }

    public function messages(): array
    {
        return [
            'fecha_programada.required' => 'Indica el nuevo día acordado con la familia.',
            'fecha_programada.date_format' => 'Indica una fecha válida.',
            'fecha_programada.after_or_equal' => 'La nueva fecha no puede ser pasada.',
        ];
    }
}
