<?php

namespace App\Http\Requests\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Rules\ImagenLegible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFotoMascotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'foto' => ['bail', 'required', 'image', 'max:5120', new ImagenLegible],
        ];
    }

    /** El límite se comprueba antes de procesar la imagen; el controlador lo repite con la mascota bloqueada. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $mascota = $this->route('mascota');

            if ($mascota instanceof Mascota
                && $mascota->fotos()->where('tipo', 'foto')->count() >= FotoMascota::MAXIMO_POR_MASCOTA) {
                $validator->errors()->add('foto', FotoMascota::MENSAJE_LIMITE);
            }
        }];
    }
}
