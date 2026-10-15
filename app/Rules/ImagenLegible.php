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

        if ($problema !== null) {
            $fail($problema);
        } elseif (! ImagenSegura::puedeLeer($value)) {
            $fail('El archivo no es una imagen válida o está dañado.');
        }
    }
}
