<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('fecha_inicio') && $this->filled('fecha_fin')
                && $this->date('fecha_inicio')->greaterThan($this->date('fecha_fin'))) {
                $validator->errors()->add('fecha_inicio', 'La fecha inicial no puede ser posterior a la fecha final.');
            }
        });
    }
}
