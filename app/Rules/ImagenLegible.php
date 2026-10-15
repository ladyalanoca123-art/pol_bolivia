<?php

namespace App\Rules;

use App\Services\ImagenSegura;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * La imagen debe poder leerse con GD (App\Services\ImagenSegura). La regla "image" solo mira el tipo
 * del archivo: una imagen dañada la supera y después no se puede regenerar.
 */
class ImagenLegible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile && ! ImagenSegura::puedeLeer($value)) {
            $fail('El archivo no es una imagen válida o está dañado.');
        }
    }
}
