<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitaRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['fecha_programada' => ['required', 'date'], 'observaciones' => ['nullable', 'string'], 'estado_salud' => ['nullable', 'string', 'max:50']];
    }
}
