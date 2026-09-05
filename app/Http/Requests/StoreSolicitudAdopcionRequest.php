<?php

namespace App\Http\Requests;

use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudAdopcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tiene_otras_mascotas' => ['required', 'boolean'],
            'viven_otras_personas' => ['required', 'boolean'],
            'tiene_espacio_adecuado' => ['required', 'boolean'],
            'zona_aproximada' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/'],
            'horario_preferido' => ['required', 'string', 'max:150', 'not_regex:/^\s*$/'],
            'comentarios_solicitante' => ['nullable', 'string'],
            'declara_informacion_veraz' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $mascota = $this->route('mascota');

            if (! $mascota instanceof Mascota || $mascota->estado !== 'disponible') {
                $validator->errors()->add('mascota', 'La mascota ya no está disponible para adopción.');

                return;
            }

            if ($this->user()?->solicitudesAdopcion()
                ->where('mascota_id', $mascota->id)
                ->whereIn('estado', ['pendiente', 'en_espera'])
                ->exists()) {
                $validator->errors()->add('mascota', 'Ya tienes una solicitud activa para esta mascota.');
            }
        }];
    }
}
