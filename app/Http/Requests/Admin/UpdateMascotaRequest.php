<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMascotaRequest extends FormRequest
{
    use Concerns\NormalizesMascotaText;
    use Concerns\ValidaFotosMascota;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\p{L}]+(?:[ \'\-]+[\p{L}]+)*$/u'],
            'especie' => ['required', Rule::in(['perro', 'gato'])],
            'raza' => ['nullable', 'string', 'max:100', 'regex:/^[\p{L}]+(?:[ \'\-]+[\p{L}]+)*$/u'],
            'edad' => ['required', 'integer', 'min:1', Rule::when($this->input('edad_en') === 'meses', ['max:11'])],
            'edad_en' => ['required', Rule::in(['meses', 'años'])],
            'sexo' => ['required', Rule::in(['macho', 'hembra'])],
            'color' => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}]+(?:[ \'\-]+[\p{L}]+)*$/u'],
            'tamano' => ['required', Rule::in(['pequeno', 'mediano', 'grande'])],
            'descripcion' => [Rule::requiredIf($this->has('descripcion')), 'string', 'not_regex:/^\s*$/u'],
        ];
    }
}
