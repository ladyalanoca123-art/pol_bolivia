<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Fechas sugeridas y márgenes para el seguimiento, contados desde la entrega real. */
class FechasSeguimientoAdopcion
{
    /**
     * @return array<int, array{dias: int, fecha: string, desde: string, hasta: string}>
     */
    public static function sugeridas(CarbonInterface $entrega): array
    {
        $base = CarbonImmutable::instance($entrega);
        $fechas = [];

        foreach (config('pol.seguimiento.visitas', []) as $numero => $regla) {
            $fechas[(int) $numero] = [
                'dias' => (int) $regla['dias_sugeridos'],
                'fecha' => $base->addDays((int) $regla['dias_sugeridos'])->toDateString(),
                'desde' => $base->addDays((int) $regla['margen_minimo'])->toDateString(),
                'hasta' => $base->addDays((int) $regla['margen_maximo'])->toDateString(),
            ];
        }

        return $fechas;
    }

    public static function dentroDelMargen(string $fecha, array $margen): bool
    {
        return $fecha >= $margen['desde'] && $fecha <= $margen['hasta'];
    }
}
