<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registrar entrega: fecha real (no futura ni anterior a la aprobación) y compromiso de adopción firmado,
 * obligatorio. PostgreSQL exige lo mismo (validar_cambio_estado_solicitud).
 */
class RegistrarEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $aprobada = $this->route('solicitud')?->aprobada_at?->toDateString();

        return [
            'fecha_entrega' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', ...($aprobada ? ["after_or_equal:{$aprobada}"] : [])],
            'compromiso_firmado' => ['accepted'],
            'permitir_otra_adopcion' => ['nullable', 'boolean'],
            'comentarios_admin' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_entrega.required' => 'Indica la fecha en que se entregó la mascota.',
            'fecha_entrega.date_format' => 'Indica una fecha válida.',
            'fecha_entrega.before_or_equal' => 'La fecha de entrega no puede ser futura.',
            'fecha_entrega.after_or_equal' => 'La fecha de entrega no puede ser anterior a la aprobación.',
            'compromiso_firmado.accepted' => 'Para registrar la entrega, el adoptante debe firmar el compromiso de adopción.',
            'comentarios_admin.max' => 'El comentario puede tener como máximo 1000 caracteres.',
        ];
    }
}
