<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidenciaRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['tipo' => ['required', Rule::in(['salud', 'maltrato', 'perdida_contacto', 'otro'])], 'descripcion' => ['required', 'string', 'not_regex:/^\s*$/'], 'visita_id' => ['nullable', 'integer']];
    }
}
