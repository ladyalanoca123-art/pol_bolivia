<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Mensaje para las personas a partir de un error de las reglas de PostgreSQL. Solo traduce las reglas del
 * sistema (RAISE EXCEPTION, SQLSTATE P0001, con mensajes en español) y las solicitudes duplicadas
 * (índices únicos, 23505); cualquier otro error devuelve null y sigue el manejo normal.
 */
class ErrorBaseDatos
{
    public static function mensaje(QueryException $exception): ?string
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $detalle = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        if ($sqlState === 'P0001' && preg_match('/ERROR:\s+([^\r\n]+)/u', $detalle, $m)) {
            return rtrim($m[1], '.').'.';
        }

        if ($sqlState === '23505') {
            return match (true) {
                str_contains($detalle, 'idx_una_solicitud_aprobada_por_mascota') => 'Esta mascota ya tiene otra solicitud aprobada.',
                str_contains($detalle, 'idx_una_solicitud_pendiente') => 'Ya hay una solicitud activa de esta persona para esta mascota.',
                default => null,
            };
        }

        return null;
    }
}
