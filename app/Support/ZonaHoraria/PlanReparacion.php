<?php

namespace App\Support\ZonaHoraria;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Plan cerrado de la reparación de zona horaria: qué filas y columnas se corrigen (−4 h) y cuáles
 * quedan excluidas. Por defecto es el plan de octubre de 2026: 29 entradas confirmadas por
 * auditoría y 31 exclusiones (12 sospechosas y 19 indeterminadas).
 *
 * Los datos de ese plan describen filas reales de pol_bolivia, por eso no se versionan: se leen de
 * un archivo local ignorado por Git (RUTA_PLAN_LOCAL, junto a los registros de la reparación) y solo
 * se aceptan si su huella es HASH_OCTUBRE_2026, la misma que guardan esos registros.
 *
 * No existe ninguna opción de consola para añadir filas: un plan distinto solo puede construirse
 * en código (las pruebas lo hacen con datos de pol_bolivia_test) y se valida igual.
 *
 * Fechas: texto UTC "Y-m-d H:i:s" sin fracciones.
 */
final class PlanReparacion
{
    public const DESFASE_SEGUNDOS = 14400;

    /** Únicas columnas que la reparación puede tocar (las de las 29 entradas confirmadas). */
    public const COLUMNAS_PERMITIDAS = [
        'usuarios' => ['created_at', 'updated_at'],
        'solicitudes_adopcion' => ['created_at', 'updated_at'],
        'postulaciones_voluntariado' => ['created_at', 'revisado_at'],
        'mascotas' => ['created_at', 'updated_at'],
    ];

    /** Nombre del modelo en la tabla auditorias. */
    public const MODELOS = [
        'usuarios' => 'User',
        'solicitudes_adopcion' => 'SolicitudAdopcion',
        'postulaciones_voluntariado' => 'PostulacionVoluntariado',
        'mascotas' => 'Mascota',
    ];

    /** Campos (sin datos personales) que se comparan uno a uno para comprobar la integridad de la fila. */
    public const CAMPOS_FILA = [
        'usuarios' => ['rol', 'estado', 'email_verified_at', 'created_at', 'updated_at'],
        'solicitudes_adopcion' => ['solicitante_id', 'mascota_id', 'estado', 'nivel_alerta', 'created_at', 'updated_at'],
        'postulaciones_voluntariado' => ['usuario_id', 'estado', 'revisado_por', 'revisado_at', 'created_at', 'updated_at'],
        'mascotas' => ['estado', 'registrado_por', 'created_at', 'updated_at'],
    ];

    /** Columnas de fecha (se comparan como texto UTC). */
    public const CAMPOS_FECHA = ['email_verified_at', 'created_at', 'updated_at', 'revisado_at'];

    /** Huella del plan de octubre de 2026 (la de sus registros de reparación). No revela sus datos. */
    public const HASH_OCTUBRE_2026 = '98d53efc2bc3d44e5d9b16b1d76b1991abbf564601b3d0a2d1169822a7c69b8e';

    /** Archivo local con los datos del plan de octubre de 2026, relativo a la raíz del proyecto. */
    public const RUTA_PLAN_LOCAL = 'storage/app/private/zona-horaria/plan-octubre-2026.php';

    private array $entradas;

    private array $exclusiones;

    private int $total;

    public function __construct(?array $entradas = null, ?array $exclusiones = null, ?int $total = null)
    {
        $local = ($entradas === null || $exclusiones === null || $total === null) ? self::cargarPlanLocal() : null;

        $this->entradas = $entradas ?? $local['entradas'];
        $this->exclusiones = $exclusiones ?? $local['exclusiones'];
        $this->total = $total ?? $local['total'];
    }

    /**
     * Lee el plan local y lo acepta solo si su huella es la esperada (por defecto HASH_OCTUBRE_2026).
     *
     * @return array{entradas: array, exclusiones: array, total: int}
     */
    public static function cargarPlanLocal(?string $ruta = null, string $hashEsperado = self::HASH_OCTUBRE_2026): array
    {
        $ruta ??= dirname(__DIR__, 3).'/'.self::RUTA_PLAN_LOCAL;

        if (! is_file($ruta)) {
            throw new RuntimeException('No se encontró el plan local de zona horaria ('.self::RUTA_PLAN_LOCAL.'): sus datos no se versionan; cópialo desde el respaldo de la reparación.');
        }

        $datos = require $ruta;

        if (! is_array($datos) || ! is_array($datos['entradas'] ?? null) || ! is_array($datos['exclusiones'] ?? null) || ! is_int($datos['total'] ?? null)) {
            throw new RuntimeException('El plan local de zona horaria no tiene el formato esperado.');
        }

        if (! hash_equals($hashEsperado, self::huella($datos['entradas'], $datos['exclusiones'], $datos['total']))) {
            throw new RuntimeException('El plan local de zona horaria no coincide con la huella esperada: no se usa.');
        }

        return ['entradas' => $datos['entradas'], 'exclusiones' => $datos['exclusiones'], 'total' => $datos['total']];
    }

    public function entradas(): array
    {
        return $this->entradas;
    }

    public function exclusiones(): array
    {
        return $this->exclusiones;
    }

    public function total(): int
    {
        return $this->total;
    }

    /** Huella del plan completo: la aplicación exige confirmarla con --confirmar-plan. */
    public function hash(): string
    {
        return self::huella($this->entradas, $this->exclusiones, $this->total);
    }

    private static function huella(array $entradas, array $exclusiones, int $total): string
    {
        return hash('sha256', json_encode(['entradas' => $entradas, 'exclusiones' => $exclusiones, 'total' => $total]));
    }

    /** Errores de coherencia del propio plan (antes de mirar la base de datos). */
    public function errores(): array
    {
        $errores = [];

        if (count($this->entradas) !== $this->total) {
            $errores[] = 'El plan tiene '.count($this->entradas)." entradas y debe tener exactamente {$this->total}.";
        }

        $claves = [];
        $excluidas = array_flip(array_map(fn ($e) => self::clave($e), $this->exclusiones));

        foreach ($this->entradas as $i => $e) {
            $etiqueta = 'Entrada '.($i + 1);

            foreach (['tabla', 'columna', 'id', 'actual', 'nuevo', 'evidencia', 'fila'] as $campo) {
                if (! isset($e[$campo])) {
                    $errores[] = "{$etiqueta}: falta '{$campo}'.";

                    continue 2;
                }
            }

            $clave = self::clave($e);
            $etiqueta .= " ({$clave})";

            if (! in_array($e['columna'], self::COLUMNAS_PERMITIDAS[$e['tabla']] ?? [], true)) {
                $errores[] = "{$etiqueta}: columna no permitida.";
            }
            if (isset($claves[$clave])) {
                $errores[] = "{$etiqueta}: duplicada.";
            }
            $claves[$clave] = true;

            if (isset($excluidas[$clave])) {
                $errores[] = "{$etiqueta}: está en la lista de exclusiones y no puede repararse.";
            }

            $actual = self::instante($e['actual']);
            $nuevo = self::instante($e['nuevo']);
            if ($actual === null || $nuevo === null || $actual->getTimestamp() - $nuevo->getTimestamp() !== self::DESFASE_SEGUNDOS) {
                $errores[] = "{$etiqueta}: el valor nuevo debe ser exactamente 4 horas antes del actual.";
            }
            if (($e['evidencia']['valor'] ?? null) !== $e['nuevo']) {
                $errores[] = "{$etiqueta}: la evidencia de auditoría no coincide con el valor nuevo.";
            }
            if (! in_array($e['evidencia']['tipo'] ?? null, ['instante', 'valor'], true) || ! isset($e['evidencia']['auditoria_id'])) {
                $errores[] = "{$etiqueta}: evidencia incompleta.";
            }
            if (! isset($e['fila']['campos'], $e['fila']['md5'])) {
                $errores[] = "{$etiqueta}: faltan los datos de integridad de la fila.";
            }
        }

        return $errores;
    }

    public static function clave(array $entrada): string
    {
        return "{$entrada['tabla']}.{$entrada['columna']}#{$entrada['id']}";
    }

    /**
     * Construye una entrada leyendo el estado actual de la base. Lo usan las pruebas (y sirvió para
     * generar el plan de octubre de 2026); no está disponible desde la consola.
     * Requiere la sesión en UTC (SET LOCAL TimeZone = 'UTC') para que el texto sea determinista.
     */
    public static function entradaDesdeBase(string $tabla, string $columna, int $id, int $auditoriaId, string $tipoEvidencia): array
    {
        $actual = DB::selectOne("select to_char({$columna} at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') v from {$tabla} where id = ?", [$id])->v;
        $nuevo = self::instante($actual)->subSeconds(self::DESFASE_SEGUNDOS)->format('Y-m-d H:i:s');
        $auditoria = DB::selectOne('select created_at::text c, valores_nuevos ->> ? v from auditorias where id = ?', [$columna, $auditoriaId]);

        return [
            'tabla' => $tabla,
            'columna' => $columna,
            'id' => $id,
            'actual' => $actual,
            'nuevo' => $nuevo,
            'evidencia' => [
                'auditoria_id' => $auditoriaId,
                'tipo' => $tipoEvidencia,
                'valor' => $tipoEvidencia === 'instante' ? substr($auditoria->c, 0, 19) : substr((string) $auditoria->v, 0, 19),
            ],
            'fila' => ReparacionZonaHoraria::estadoFila($tabla, $id),
        ];
    }

    private static function instante(string $texto): ?Carbon
    {
        $fecha = Carbon::createFromFormat('Y-m-d H:i:s', $texto, 'UTC');

        return $fecha && $fecha->format('Y-m-d H:i:s') === $texto ? $fecha : null;
    }
}
