<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::in(['administrador', 'voluntario', 'ciudadano'])],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ];
    }
}
