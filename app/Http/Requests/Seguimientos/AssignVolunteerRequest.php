<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seguimiento = $this->route('seguimiento');
        return auth()->user()?->can('assign', $seguimiento) ?? false;
    }

    public function rules(): array
    {
        // El adoptante no puede ser el voluntario de su propio seguimiento (conflicto de interés).
        $adoptanteId = $this->route('seguimiento')->solicitud->solicitante_id;

        return ['voluntario_id' => [
            'required',
            'integer',
            Rule::exists('usuarios', 'id')->where(fn ($query) => $query->where('rol', 'voluntario')->where('estado', true)),
            Rule::notIn([$adoptanteId]),
        ]];
    }

    public function messages(): array
    {
        return ['voluntario_id.not_in' => 'El adoptante no puede hacer el seguimiento de su propia adopción.'];
    }
}
