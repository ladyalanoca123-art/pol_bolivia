<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMascotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/'],
            'especie' => ['required', Rule::in(['perro', 'gato'])],
            'raza' => ['nullable', 'string', 'max:100'],
            'edad' => ['required', 'integer', 'min:0'],
            'sexo' => ['required', Rule::in(['macho', 'hembra'])],
            'color' => ['nullable', 'string', 'max:50'],
            'tamano' => ['required', Rule::in(['pequeno', 'mediano', 'grande'])],
            'descripcion' => ['nullable', 'string'],
        ];
    }
}
