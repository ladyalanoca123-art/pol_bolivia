<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Validation\Rule;

/**
 * Cambiar el voluntario de un seguimiento activo (no puede o no responde). Mismas reglas que al asignar
 * (voluntario activo y que no sea el adoptante) y, además, debe ser otro voluntario distinto del actual.
 */
class ReassignVolunteerRequest extends AssignVolunteerRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('reassign', $this->route('seguimiento')) ?? false;
    }

    public function rules(): array
    {
        $reglas = parent::rules();
        $reglas['voluntario_id'][] = Rule::notIn([$this->route('seguimiento')->voluntario_id]);

        return $reglas;
    }

    public function messages(): array
    {
        return ['voluntario_id.not_in' => 'Elige un voluntario distinto del actual (y que no sea el adoptante).'];
    }
}
