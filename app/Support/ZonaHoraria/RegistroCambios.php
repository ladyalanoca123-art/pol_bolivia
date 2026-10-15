<?php

namespace App\Support\ZonaHoraria;

use RuntimeException;

/**
 * Registro (JSON firmado) de una reparación o reversión: base, plan, zonas, horas y, por cada
 * columna, el valor original exacto y el nuevo. Permite auditar y revertir cada cambio.
 *
 * La firma (SHA-256 del contenido) detecta modificaciones accidentales o manuales del archivo, pero
 * no es una firma criptográfica: por eso la reversión exige además que los cambios coincidan con el
 * plan fijo (ReparacionZonaHoraria::erroresCambiosDelPlan).
 * Cada escritura es atómica (archivo temporal + fsync + rename): nunca queda un registro a medias.
 */
final class RegistroCambios
{
    public static function rutaPorDefecto(string $tipo, string $base): string
    {
        return storage_path('app/private/zona-horaria/'.$tipo.'-'.$base.'-'.now('UTC')->format('Ymd_His').'.json');
    }

    /** Crea un registro nuevo; nunca sobrescribe uno existente (podría ser el de otra reparación). */
    public static function crear(string $ruta, array $datos): void
    {
        if (file_exists($ruta)) {
            throw new RuntimeException("Ya existe el registro {$ruta}: no se sobrescribe.");
        }

        self::guardar($ruta, $datos);
    }

    public static function guardar(string $ruta, array $datos): void
    {
        unset($datos['firma']);
        $datos['firma'] = hash('sha256', json_encode($datos));

        $carpeta = dirname($ruta);
        if (! is_dir($carpeta) && ! mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            throw new RuntimeException("No se pudo crear la carpeta {$carpeta}.");
        }

        $temporal = $ruta.'.tmp-'.bin2hex(random_bytes(4));
        $archivo = fopen($temporal, 'xb');
        $escrito = $archivo !== false
            && fwrite($archivo, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false
            && fflush($archivo)
            && fsync($archivo);
        if ($archivo !== false) {
            fclose($archivo);
        }

        if (! $escrito || ! rename($temporal, $ruta)) {
            @unlink($temporal);

            throw new RuntimeException("No se pudo escribir el registro {$ruta}.");
        }
    }

    /** Lee y valida la firma. */
    public static function leer(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new RuntimeException("No existe el registro {$ruta}.");
        }

        $datos = json_decode((string) file_get_contents($ruta), true);
        if (! is_array($datos) || ! isset($datos['firma'])) {
            throw new RuntimeException('El registro no tiene un formato válido.');
        }

        $firma = $datos['firma'];
        unset($datos['firma']);
        if (! is_string($firma) || ! hash_equals(hash('sha256', json_encode($datos)), $firma)) {
            throw new RuntimeException('La firma del registro no coincide: el archivo fue modificado.');
        }

        return $datos;
    }
}
