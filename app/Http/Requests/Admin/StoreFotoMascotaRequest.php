<?php

namespace App\Http\Requests\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Fotos nuevas de una mascota: varias en "fotos[]" o, por compatibilidad, una sola en "foto".
 * Si la selección no cabe en los espacios libres se rechaza entera: nunca se guarda una parte.
 */
class StoreFotoMascotaRequest extends FormRequest
{
    use Concerns\ValidaFotosMascota;

    private ?int $espaciosLibres = null;

    public function authorize(): bool
    {
        return true;
    }

    /** Campo con el que llegan las fotos: "fotos" o el antiguo "foto". */
    public function campo(): string
    {
        return ! $this->has('fotos') && $this->hasFile('foto') ? 'foto' : 'fotos';
    }

    protected function prepareForValidation(): void
    {
        $this->quitarArchivosVacios('fotos');
    }

    public function rules(): array
    {
        if ($this->campo() === 'foto') {
            return ['foto' => self::reglasFoto()];
        }

        $reglas = ['fotos' => ['required', 'array', 'min:1', 'max:'.$this->espaciosLibres()]];

        // Cada foto se valida aparte y se informan los errores de todas ("Foto n.º 2: …"). Si la selección
        // no cabe se rechaza entera sin abrir ninguna imagen.
        $archivos = $this->file('fotos');

        if ((is_array($archivos) ? count($archivos) : 1) <= $this->espaciosLibres()) {
            $reglas['fotos.*'] = self::reglasFoto();
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            ...self::mensajesFoto('foto'),
            ...self::mensajesFoto('fotos.*'),
            'fotos.required' => 'Selecciona al menos una foto.',
            'fotos.min' => 'Selecciona al menos una foto.',
            'fotos.max' => FotoMascota::mensajeEspaciosLibres($this->espaciosLibres()),
        ];
    }

    /** El límite se comprueba antes de procesar la imagen; el controlador lo repite con la mascota bloqueada. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->campo() === 'foto' && $this->espaciosLibres() === 0) {
                $validator->errors()->add('foto', FotoMascota::MENSAJE_LIMITE);
            }
        }];
    }

    /** Fotos que todavía se pueden agregar a la mascota. */
    public function espaciosLibres(): int
    {
        $mascota = $this->route('mascota');

        return $this->espaciosLibres ??= $mascota instanceof Mascota
            ? max(0, FotoMascota::MAXIMO_POR_MASCOTA - $mascota->fotos()->where('tipo', 'foto')->count())
            : FotoMascota::MAXIMO_POR_MASCOTA;
    }
}
