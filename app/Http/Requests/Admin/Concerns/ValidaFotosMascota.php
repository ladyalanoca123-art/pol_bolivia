<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Rules\ImagenLegible;

/**
 * Reglas, mensajes y nombres de campo en español compartidos por los formularios de Mascotas.
 * Cada foto se valida igual en el registro, en la ficha y en el campo antiguo "foto".
 */
trait ValidaFotosMascota
{
    /**
     * Reglas de una foto: JPG, PNG o WEBP y peso máximo (config/pol.php) antes de leerla; después ImagenLegible:
     * medidas reales de la cabecera (mínimo App\Services\ImagenSegura::LADO_MINIMO por lado, con un mensaje que indica las
     * medidas de la imagen), megapíxeles y que GD pueda leerla. Por eso no se usa la regla "dimensions".
     */
    protected static function reglasFoto(): array
    {
        return [
            'bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp',
            'max:'.config('pol.mascotas.foto_max_kb'),
            new ImagenLegible,
        ];
    }

    /** Mensajes de las reglas de reglasFoto() para $campo (admite comodines, p. ej. "fotos.*"). */
    protected static function mensajesFoto(string $campo): array
    {
        $megas = self::megasMaximos();

        return [
            "{$campo}.uploaded" => "La imagen :attribute no se pudo subir. Puede que supere el tamaño permitido por el servidor ({$megas} MB).",
            "{$campo}.image" => 'El archivo :attribute debe ser una imagen.',
            "{$campo}.mimes" => 'La imagen :attribute debe ser JPG, PNG o WEBP.',
            "{$campo}.max" => "La imagen :attribute no debe pesar más de {$megas} MB.",
        ];
    }

    /** Peso máximo de una foto en MB, para los mensajes y los textos de ayuda. */
    public static function megasMaximos(): string
    {
        return rtrim(rtrim(number_format(config('pol.mascotas.foto_max_kb') / 1024, 1, ',', ''), '0'), ',');
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'especie' => 'especie',
            'raza' => 'raza',
            'edad' => 'edad',
            'edad_en' => 'unidad de edad',
            'sexo' => 'sexo',
            'color' => 'color',
            'tamano' => 'tamaño',
            'descripcion' => 'descripción',
            'estado' => 'estado',
            'buscar' => 'búsqueda',
            'foto_principal' => 'foto principal',
            'foto' => 'foto',
            'fotos' => 'fotos',
            'fotos.*' => 'foto n.º :position',
            'fotos_adicionales' => 'fotos adicionales',
            'fotos_adicionales.*' => 'foto adicional n.º :position',
        ];
    }

    /** Quita de la lista $campo los elementos vacíos que envía un selector de archivos múltiple sin archivos. */
    protected function quitarArchivosVacios(string $campo): void
    {
        if (is_array($archivos = $this->files->get($campo))) {
            $this->files->set($campo, array_values(array_filter($archivos)));
        }
    }
}
