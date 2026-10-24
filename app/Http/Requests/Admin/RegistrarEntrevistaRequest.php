<?php

namespace App\Http\Requests\Admin;

use App\Models\SolicitudAdopcion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Entrevista con el solicitante (etapa «En evaluación»). Puede corregirse mientras siga en evaluación. */
class RegistrarEntrevistaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entrevista_fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'entrevista_medio' => ['required', Rule::in(array_keys(SolicitudAdopcion::MEDIOS_ENTREVISTA))],
            'entrevista_resultado' => ['required', Rule::in(array_keys(SolicitudAdopcion::RESULTADOS))],
            'entrevista_notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'entrevista_fecha.required' => 'Indica la fecha de la entrevista.',
            'entrevista_fecha.date_format' => 'Indica una fecha válida.',
            'entrevista_fecha.before_or_equal' => 'La fecha de la entrevista no puede ser futura.',
            'entrevista_medio.required' => 'Indica cómo fue la entrevista.',
            'entrevista_medio.in' => 'Selecciona una opción válida.',
            'entrevista_resultado.required' => 'Indica el resultado de la entrevista.',
            'entrevista_resultado.in' => 'Selecciona una opción válida.',
            'entrevista_notas.max' => 'Las notas pueden tener como máximo 2000 caracteres.',
        ];
    }
}
