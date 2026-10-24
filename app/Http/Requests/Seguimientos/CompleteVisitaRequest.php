<?php

namespace App\Http\Requests\Seguimientos;

use App\Models\VisitaSeguimiento;
use App\Support\CuestionarioVisitaSeguimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        /** @var VisitaSeguimiento $visita */
        $visita = $this->route('visita');

        $reglas = CuestionarioVisitaSeguimiento::reglas(CuestionarioVisitaSeguimiento::numero($visita));
        $reglas['fecha_realizada'][] = 'after_or_equal:'.$visita->fecha_programada->toDateString();

        return $reglas;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sintomas = $this->input('respuestas.sintomas', []);
            if (is_array($sintomas) && in_array('ninguno', $sintomas, true) && count($sintomas) > 1) {
                $validator->errors()->add('respuestas.sintomas', 'Selecciona “Ninguno” o los síntomas observados, pero no ambos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'fecha_realizada.required' => 'Indica la fecha en que se realizó la visita.',
            'fecha_realizada.before_or_equal' => 'La fecha de la visita no puede ser futura.',
            'fecha_realizada.after_or_equal' => 'La fecha realizada no puede ser anterior a la fecha programada.',
            'respuestas.*.required' => 'Responde esta pregunta para registrar la visita.',
            'respuestas.*.in' => 'Selecciona una opción válida.',
            'respuestas.sintomas.max' => 'Selecciona como máximo :max opciones.',
            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.mimes' => 'La foto debe estar en formato JPG, PNG o WebP.',
            'foto.max' => 'La foto no puede superar los 5 MB.',
        ];
    }
}
