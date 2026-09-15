<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seguimiento = $this->route('seguimiento');
        return auth()->user()?->can('assign', $seguimiento) ?? false;
    }

    public function rules(): array
    {
        return ['voluntario_id' => ['required', 'integer', Rule::exists('usuarios', 'id')->where(fn ($query) => $query->where('rol', 'voluntario')->where('estado', true))]];
    }
}
