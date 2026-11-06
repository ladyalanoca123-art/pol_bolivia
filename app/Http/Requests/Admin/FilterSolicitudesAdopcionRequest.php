<?php

namespace App\Http\Requests\Admin;

use App\Models\SolicitudAdopcion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterSolicitudesAdopcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            // Un estado concreto o «cerradas» (rechazadas, canceladas, no concretadas y cerradas juntas).
            'estado' => ['nullable', Rule::in([...array_keys(SolicitudAdopcion::ESTADOS_LEGIBLES), 'cerradas'])],
            'nivel_alerta' => ['nullable', Rule::in(['verde', 'amarillo', 'rojo'])],
        ];
    }
}
