<?php

namespace App\Rules;

use App\Services\ImagenSegura;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * La imagen debe poder procesarse con App\Services\ImagenSegura. La regla "image" solo mira el tipo del
 * archivo: aquí se comprueban sus dimensiones (sin decodificarla) y, después, que GD pueda leerla.
 */
class ImagenLegible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $problema = ImagenSegura::problema($value->getRealPath());

        if ($problema === null && ! ImagenSegura::puedeLeer($value)) {
            $problema = 'El archivo no es una imagen válida o está dañado.';
        }

        if ($problema !== null) {
            // En una selección de varias fotos ("fotos.1") se indica cuál es: "Foto n.º 2: …".
            $fail(str_contains($attribute, '.') ? ":Attribute: {$problema}" : $problema);
        }
    }
}
