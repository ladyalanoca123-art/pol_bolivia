<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros de la lista de seguimientos: búsqueda por mascota o familia, estado y voluntario. */
class FilterSeguimientosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['sin_asignar', 'activo', 'cerrado'])],
            'voluntario' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
