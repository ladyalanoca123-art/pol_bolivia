<?php

namespace App\Http\Requests\Admin;

use App\Models\SolicitudAdopcion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Visita previa al hogar (opcional, etapa «En evaluación»). Si no es favorable, no se puede aprobar. */
class RegistrarVisitaPreviaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visita_previa_fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'visita_previa_resultado' => ['required', Rule::in(array_keys(SolicitudAdopcion::RESULTADOS))],
            'visita_previa_notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'visita_previa_fecha.required' => 'Indica la fecha de la visita.',
            'visita_previa_fecha.date_format' => 'Indica una fecha válida.',
            'visita_previa_fecha.before_or_equal' => 'La fecha de la visita no puede ser futura.',
            'visita_previa_resultado.required' => 'Indica el resultado de la visita.',
            'visita_previa_resultado.in' => 'Selecciona una opción válida.',
            'visita_previa_notas.max' => 'Las notas pueden tener como máximo 2000 caracteres.',
        ];
    }
}
