<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

class AttendIncidenciaRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['comentarios_resolucion' => ['required', 'string', 'not_regex:/^\s*$/']]; }
}
