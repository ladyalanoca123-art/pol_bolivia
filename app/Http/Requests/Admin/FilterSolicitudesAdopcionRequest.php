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
            'estado' => ['nullable', Rule::in(array_keys(SolicitudAdopcion::ESTADOS_LEGIBLES))],
            'nivel_alerta' => ['nullable', Rule::in(['verde', 'amarillo', 'rojo'])],
        ];
    }
}
