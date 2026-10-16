<?php

namespace App\Http\Requests\Admin;

use App\Models\FotoMascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMascotaRequest extends FormRequest
{
    use Concerns\NormalizesMascotaText {
        prepareForValidation as normalizarTextos;
    }
    use Concerns\ValidaFotosMascota;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarTextos();
        $this->quitarArchivosVacios('fotos_adicionales');
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\p{L}]+(?:[ \'\-]+[\p{L}]+)*$/u'],
            'especie' => ['required', Rule::in(['perro', 'gato'])],
            'raza' => ['nullable', 'string', 'max:100', 'regex:/^[\p{L}]+(?:[ \'\-]+[\p{L}]+)*$/u'],
            'edad' => ['required', 'integer', 'min:1', Rule::when($this->input('edad_en') === 'meses', ['max:11'])],
            'edad_en' => ['required', Rule::in(['meses', 'años'])],
            'sexo' => ['required', Rule::in(['macho', 'hembra'])],
            'color' => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}]+(?:[ \'\-]+[\p{L}]+)*$/u'],
            'tamano' => ['required', Rule::in(['pequeno', 'mediano', 'grande'])],
            'descripcion' => [Rule::requiredIf($this->has('descripcion')), 'string', 'not_regex:/^\s*$/u'],
            'foto_principal' => self::reglasFoto(),
            // Opcionales: con la principal completan el máximo de fotos por mascota.
            'fotos_adicionales' => ['nullable', 'array', 'max:'.(FotoMascota::MAXIMO_POR_MASCOTA - 1)],
            'fotos_adicionales.*' => self::reglasFoto(),
        ];
    }

    public function messages(): array
    {
        return [
            ...self::mensajesFoto('foto_principal'),
            ...self::mensajesFoto('fotos_adicionales.*'),
            'fotos_adicionales.max' => 'Puedes subir como máximo :max fotos adicionales (4 en total con la principal).',
        ];
    }
}
