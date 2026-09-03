<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterMascotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'especie' => ['nullable', Rule::in(['perro', 'gato'])],
            'sexo' => ['nullable', Rule::in(['macho', 'hembra'])],
            'tamano' => ['nullable', Rule::in(['pequeno', 'mediano', 'grande'])],
            'estado' => ['nullable', Rule::in(['borrador', 'disponible', 'adoptada'])],
        ];
    }
}
