<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Entrega no concretada: el motivo es obligatorio (PostgreSQL también lo exige). */
class EntregaNoConcretadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_no_concretada' => ['required', 'string', 'min:5', 'max:1000', 'not_regex:/^\s*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_no_concretada.required' => 'Escribe el motivo por el que no se concretó la entrega.',
            'motivo_no_concretada.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo_no_concretada.not_regex' => 'Escribe el motivo por el que no se concretó la entrega.',
            'motivo_no_concretada.max' => 'El motivo puede tener como máximo 1000 caracteres.',
        ];
    }
}
