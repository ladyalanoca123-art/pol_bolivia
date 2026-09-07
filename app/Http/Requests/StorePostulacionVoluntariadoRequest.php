<?php

namespace App\Http\Requests;

use App\Models\PostulacionVoluntariado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePostulacionVoluntariadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivacion' => ['required', 'string', 'max:5000'],
            'habilidades' => ['nullable', 'string', 'max:5000'],
            'disponibilidad' => ['required', 'string', 'max:150'],
            'zona_preferencia' => ['required', 'string', 'max:100'],
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
