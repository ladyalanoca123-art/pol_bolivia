<?php

namespace App\Support\ZonaHoraria;

use RuntimeException;

/** Alguna fila no coincide con lo esperado: la operación se aborta sin modificar nada. */
final class DiscrepanciaException extends RuntimeException
{
    /** @param array<string, array<int, string>> $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('Discrepancias detectadas: '.count($errores).' entrada(s).');
    }
}
