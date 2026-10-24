<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadCommitmentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadCommitmentEvidence', $this->route('solicitud')) ?? false;
    }

    public function rules(): array
    {
        return [
            'evidencia_compromiso' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'evidencia_compromiso.required' => 'Selecciona la evidencia del compromiso firmado.',
            'evidencia_compromiso.file' => 'El archivo seleccionado no es válido.',
            'evidencia_compromiso.max' => 'La evidencia no puede superar los 10 MB.',
            'evidencia_compromiso.mimes' => 'La evidencia debe ser una imagen JPG o PNG, o un PDF.',
            'evidencia_compromiso.mimetypes' => 'El contenido del archivo no coincide con un JPG, PNG o PDF válido.',
        ];
    }
}
