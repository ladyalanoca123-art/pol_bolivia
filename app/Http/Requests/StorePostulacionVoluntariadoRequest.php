<?php

namespace App\Http\Requests;

use App\Models\PostulacionVoluntariado;
use App\Support\CuestionarioVoluntariado as V;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el cuestionario de voluntariado V1 (10 preguntas).
 * La pregunta 3 solo aplica si la 2 es "Sí"; si no, se excluye aunque el navegador la envíe.
 */
class StorePostulacionVoluntariadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $texto = ['string', 'not_regex:/^\s*$/'];

        return [
            // Sección 1 · Motivación y experiencia
            // Límites de motivación y habilidades: los mismos del formulario anterior (5000).
            'motivacion' => ['required', ...$texto, 'max:5000'],
            'experiencia_previa' => ['required', Rule::in(V::claves('experiencia_previa'))],
            'experiencia_detalle' => ['exclude_unless:experiencia_previa,si', 'required', ...$texto, 'max:1000'],

            // Sección 2 · Áreas y disponibilidad
            'areas_interes' => ['required', 'array', 'min:1'],
            'areas_interes.*' => ['distinct', Rule::in(V::claves('areas_interes'))],
            'dias_disponibles' => ['required', 'array', 'min:1'],
            'dias_disponibles.*' => ['distinct', Rule::in(V::claves('dias_disponibles'))],
            'franjas_horarias' => ['required', 'array', 'min:1'],
            'franjas_horarias.*' => ['distinct', Rule::in(V::claves('franjas_horarias'))],
            'zona_preferencia' => ['required', ...$texto, 'max:100'],

            // Sección 3 · Compromiso
            'habilidades' => ['nullable', 'string', 'max:5000'],
            'confirma_mayor_edad' => ['accepted'],
            'declara_informacion_veraz' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivacion.required' => 'Cuéntanos por qué quieres ser voluntario/a.',
            'motivacion.not_regex' => 'Cuéntanos por qué quieres ser voluntario/a.',
            'motivacion.max' => 'La motivación no puede superar los 5000 caracteres.',
            'experiencia_previa.required' => 'Responde si tienes experiencia previa.',
            'experiencia_detalle.required' => 'Describe brevemente tu experiencia.',
            'experiencia_detalle.not_regex' => 'Describe brevemente tu experiencia.',
            'experiencia_detalle.max' => 'La descripción de tu experiencia no puede superar los 1000 caracteres.',
            'habilidades.max' => 'Las habilidades no pueden superar los 5000 caracteres.',
            'zona_preferencia.max' => 'La zona de preferencia no puede superar los 100 caracteres.',
            'areas_interes.required' => 'Marca al menos un área.',
            'dias_disponibles.required' => 'Marca al menos un día.',
            'franjas_horarias.required' => 'Marca al menos una franja horaria.',
            'zona_preferencia.required' => 'Escribe tu zona de preferencia.',
            'confirma_mayor_edad.accepted' => 'Debes confirmar que eres mayor de 18 años.',
            'declara_informacion_veraz.accepted' => 'Debes aceptar la declaración de veracidad.',
            '*.in' => 'Selecciona una opción válida.',
            '*.*.in' => 'Selecciona una opción válida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (PostulacionVoluntariado::query()
                ->where('usuario_id', $this->user()->id)
                ->where('estado', 'pendiente')
                ->exists()) {
                $validator->errors()->add('postulacion', 'Ya tienes una postulación pendiente.');
            }
        });
    }
}
