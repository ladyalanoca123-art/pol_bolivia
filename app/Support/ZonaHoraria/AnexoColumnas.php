<?php

namespace App\Support\ZonaHoraria;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anexo firmado de un registro de reparación: la lista exacta y ordenada de columnas que tenía cada
 * tabla cuando se calcularon las huellas (md5(t::text)) de las filas reparadas.
 *
 * Permite verificar la reversión después de añadir columnas nuevas al final de una tabla, sin tocar
 * el registro original ni sus huellas: la huella se recalcula con esas columnas y debe coincidir.
 *
 * Integridad, en tres capas independientes:
 *  1. HMAC-SHA256 con una clave derivada de APP_KEY: sin la clave no se puede alterar ni fabricar.
 *  2. Vínculo con el registro original: SHA-256 del archivo exacto, base y hash del plan.
 *  3. Coherencia con el esquema: la lista debe ser un prefijo exacto (mismo orden) de las columnas
 *     actuales y, al verificar, reproducir la huella original de cada fila. Quitar, reordenar o
 *     sustituir columnas hace fallar la comprobación aunque se dispusiera de la clave.
 * Se genera solo mientras las 29 huellas coinciden con la tabla completa (antes de añadir columnas).
 */
final class AnexoColumnas
{
    public const TIPO = 'anexo-columnas-reparacion';

    public static function rutaPara(string $manifiesto): string
    {
        return preg_replace('/\.json$/', '', $manifiesto).'-columnas.json';
    }

    /**
     * Construye el anexo a partir del registro y del estado actual (dentro de una transacción de solo
     * lectura con la sesión en UTC). Falla si alguna huella no coincide con la fila completa.
     */
    public static function generar(string $rutaManifiesto): array
    {
        $registro = RegistroCambios::leer($rutaManifiesto);
        if (($registro['tipo'] ?? null) !== 'reparacion' || ! in_array($registro['estado'] ?? null, ['aplicado', 'confirmando'], true)) {
            throw new RuntimeException('El registro no corresponde a una reparación aplicada.');
        }

        $base = DB::selectOne('select current_database() as b')->b;
        if (($registro['base'] ?? null) !== $base) {
            throw new RuntimeException("El registro es de la base {$registro['base']}, no de {$base}.");
        }

        $columnas = [];
        foreach (array_unique(array_column($registro['cambios'], 'tabla')) as $tabla) {
            $columnas[$tabla] = self::columnasActuales($tabla);
        }

        foreach ($registro['cambios'] as $c) {
            $completa = ReparacionZonaHoraria::estadoFila($c['tabla'], $c['id']);
            $lista = ReparacionZonaHoraria::estadoFila($c['tabla'], $c['id'], $columnas[$c['tabla']]);
            if ($completa === null || $completa['md5'] !== $c['fila_tras_cambio']['md5'] || $lista['md5'] !== $c['fila_tras_cambio']['md5']) {
                throw new RuntimeException(PlanReparacion::clave($c).': la fila ya no coincide con la huella del registro; no se puede generar el anexo.');
            }
        }

        $datos = [
            'tipo' => self::TIPO,
            'version' => 1,
            'base' => $base,
            'plan_hash' => $registro['plan_hash'],
            'manifiesto' => basename($rutaManifiesto),
            'manifiesto_sha256' => hash_file('sha256', $rutaManifiesto),
            'columnas' => $columnas,
            'huellas_comprobadas' => count($registro['cambios']),
            'creado' => now('UTC')->toIso8601String(),
        ];

        return [...$datos, 'hmac' => self::hmac($datos)];
    }

    /** Guarda un anexo nuevo (nunca sobrescribe) con la escritura atómica de RegistroCambios. */
    public static function guardar(string $ruta, array $anexo): void
    {
        RegistroCambios::crear($ruta, $anexo);
    }

    /** Lee el anexo y verifica su HMAC. */
    public static function leer(string $ruta): array
    {
        $anexo = RegistroCambios::leer($ruta);
        $hmac = $anexo['hmac'] ?? null;
        unset($anexo['hmac']);

        if (! is_string($hmac) || ! hash_equals(self::hmac($anexo), $hmac)) {
            throw new RuntimeException('El HMAC del anexo no es válido: el anexo fue modificado o no se generó con esta clave.');
        }

        return $anexo;
    }

    /**
     * Comprueba el vínculo con el registro y la coherencia con el esquema actual.
     *
     * @return array<int, string> errores (vacío si el anexo puede usarse)
     */
    public static function errores(array $anexo, string $rutaManifiesto, array $registro): array
    {
        $errores = [];
        $base = DB::selectOne('select current_database() as b')->b;

        if (($anexo['tipo'] ?? null) !== self::TIPO || ($anexo['version'] ?? null) !== 1) {
            $errores[] = 'el archivo no es un anexo de columnas (versión 1).';
        }
        if (! is_file($rutaManifiesto) || ! hash_equals((string) ($anexo['manifiesto_sha256'] ?? ''), hash_file('sha256', $rutaManifiesto))) {
            $errores[] = 'el anexo no corresponde a este registro de cambios (SHA-256 distinto).';
        }
        if (($anexo['base'] ?? null) !== $base || ($registro['base'] ?? null) !== $base) {
            $errores[] = "el anexo o el registro no son de la base {$base}.";
        }
        if (($anexo['plan_hash'] ?? null) !== ($registro['plan_hash'] ?? false)) {
            $errores[] = 'el anexo no corresponde al plan del registro.';
        }

        foreach (array_unique(array_column($registro['cambios'] ?? [], 'tabla')) as $tabla) {
            $originales = $anexo['columnas'][$tabla] ?? null;
            if (! is_array($originales) || $originales === []) {
                $errores[] = "el anexo no tiene las columnas de {$tabla}.";

                continue;
            }
            if (array_slice(self::columnasActuales($tabla), 0, count($originales)) !== array_values($originales)) {
                $errores[] = "las columnas originales de {$tabla} ya no están, en el mismo orden, al principio de la tabla.";
            }
        }

        return $errores;
    }

    /** @return array<int, string> columnas de la tabla en su orden físico */
    public static function columnasActuales(string $tabla): array
    {
        return array_column(DB::select(
            "select column_name from information_schema.columns where table_schema = 'public' and table_name = ? order by ordinal_position",
            [$tabla]
        ), 'column_name');
    }

    private static function hmac(array $datos): string
    {
        $clave = (string) config('app.key');
        if ($clave === '') {
            throw new RuntimeException('APP_KEY no está configurada: no se puede firmar ni verificar el anexo.');
        }
        if (str_starts_with($clave, 'base64:')) {
            $clave = base64_decode(substr($clave, 7), true) ?: throw new RuntimeException('APP_KEY no es base64 válido.');
        }
        // Clave propia de este uso (separación de dominio respecto del cifrado de Laravel).
        $claveAnexo = hash_hmac('sha256', 'pol-bolivia/zona-horaria/anexo-columnas/v1', $clave, true);

        return hash_hmac('sha256', json_encode($datos), $claveAnexo);
    }
}
