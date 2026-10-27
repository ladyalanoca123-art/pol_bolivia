<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

/** Marcar «No asistió»: el detalle es opcional. */
class MarkNoShowVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('work', $this->route('seguimiento')) ?? false;
    }

    public function rules(): array
    {
        return ['observaciones' => ['nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'observaciones.string' => 'El detalle no es válido.',
            'observaciones.max' => 'El detalle puede tener como máximo 500 caracteres.',
        ];
    }
}
