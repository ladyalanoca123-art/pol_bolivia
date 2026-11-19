<?php

namespace App\Support;

/**
 * Escribe nombres y apellidos con mayúscula inicial: «jose carlos COPA» → «Jose Carlos Copa».
 * También tras guion, apóstrofe y punto («pérez-gómez» → «Pérez-Gómez», «o'brien» → «O'Brien»). Las partículas
 * (de, del, la, los, y…) van en minúscula salvo al inicio: «maría DE LOS ángeles» → «María de los Ángeles».
 * Quita los espacios sobrantes. Funciona con tildes y ñ.
 */
final class NombrePropio
{
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'das', 'do', 'dos', 'di', 'van', 'von'];

    public static function formatear(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = preg_replace('/\s+/u', ' ', trim($valor));
        if ($valor === '') {
            return $valor;
        }

        $palabras = explode(' ', mb_strtolower($valor, 'UTF-8'));

        foreach ($palabras as $i => $palabra) {
            if ($i > 0 && in_array($palabra, self::PARTICULAS, true)) {
                continue;
            }
            // Mayúscula en la primera letra y en la que sigue a un guion, apóstrofe o punto («Ana.María»).
            $palabras[$i] = preg_replace_callback(
                "/(^|[\\-'’.])(\\pL)/u",
                fn (array $m) => $m[1].mb_strtoupper($m[2], 'UTF-8'),
                $palabra,
            );
        }

        return implode(' ', $palabras);
    }
}
