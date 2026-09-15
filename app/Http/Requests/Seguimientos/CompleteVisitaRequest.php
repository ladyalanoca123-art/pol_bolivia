<?php

namespace App\Http\Requests\Seguimientos;

use Illuminate\Foundation\Http\FormRequest;

class CompleteVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visita = $this->route('visita');
        $seguimiento = $visita->seguimiento;

        return auth()->user()?->can('work', $seguimiento) ?? false;
    }

    public function rules(): array
    {
        return ['fecha_realizada' => ['required', 'date'], 'observaciones' => ['nullable', 'string'], 'estado_salud' => ['nullable', 'string', 'max:50']];
    }
}
