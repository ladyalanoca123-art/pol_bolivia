<?php

namespace App\Http\Requests\Admin;

use App\Support\CuestionarioVoluntariado as V;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterPostulacionesVoluntariadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['nullable', Rule::in(['pendiente', 'aprobada', 'rechazada', 'cancelada'])],
            'area' => ['nullable', Rule::in(V::claves('areas_interes'))],
            'dia' => ['nullable', Rule::in(V::claves('dias_disponibles'))],
        ];
    }
}
