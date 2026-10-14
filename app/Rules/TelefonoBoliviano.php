<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teléfono móvil boliviano en formato nacional: 8 dígitos que comienzan por 5, 6 o 7.
 * Única regla para el registro público y el panel de administración.
 *
 * Solo comprueba el FORMATO. No garantiza que el número esté asignado ni activo: la ATT asigna
 * los rangos por resolución (p. ej., ATT-DJ-RAR-TL LP 288/2026 confirma 57 000 000–57 999 999
 * para servicio móvil nacional) y no se validan rangos concretos dentro de cada prefijo.
 *
 * Uso: normalizar() en prepareForValidation() y luego esta regla sobre el valor normalizado.
 */
class TelefonoBoliviano implements ValidationRule
{
    public const PATRON = '/^[567]\d{7}$/';

    public const MENSAJE = 'El teléfono debe tener 8 dígitos y comenzar por 5, 6 o 7 (por ejemplo, 71234567). Puedes escribirlo con +591, espacios o guiones.';

    /**
     * Quita espacios y guiones y, si viene con prefijo internacional (+591 o 591) seguido de
     * 8 dígitos, deja solo los 8 dígitos nacionales. Un valor vacío pasa a null.
     * Cualquier otro valor se devuelve sin separadores para que la regla lo rechace.
     */
    public static function normalizar(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $sinSeparadores = preg_replace('/[\s\-]+/u', '', $valor);

        if ($sinSeparadores === '') {
            return null;
        }

        // Solo con 11 dígitos tras "591": un número nacional de 8 dígitos también puede empezar por 591.
        if (preg_match('/^\+?591(\d{8})$/', $sinSeparadores, $coincidencia)) {
            return $coincidencia[1];
        }

        return $sinSeparadores;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || ! preg_match(self::PATRON, $value)) {
            $fail(self::MENSAJE);
        }
    }
}
