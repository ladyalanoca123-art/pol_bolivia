<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

class AttendIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incidencia = $this->route('incidencia');
        return auth()->user()?->can('attend', $incidencia) ?? false;
    }

    public function rules(): array
    {
        return ['comentarios_resolucion' => ['required', 'string', 'not_regex:/^\s*$/']];
    }
}
