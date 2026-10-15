<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Período de consulta de Reportes y su período anterior de comparación, calculados en America/La_Paz.
 *
 * Límites: intervalo semiabierto [inicio, finExclusivo): desde las 00:00 del primer día hasta las 00:00
 * del día siguiente al último. Así no se pierde ningún registro de las 23:59:59.999… del último día.
 *
 * Períodos (con «hoy» en La Paz):
 *  - este_mes: del día 1 del mes actual a hoy.
 *  - mes_anterior: el mes calendario anterior completo.
 *  - ultimos_3_meses: los 3 meses móviles que terminan hoy (del día siguiente a «hoy − 3 meses» a hoy;
 *    si ese día no existe, p. ej. 31/05 − 3 meses, se usa el último día de ese mes).
 *  - este_anio: del 1 de enero a hoy.
 *  - personalizado: de fecha_inicio a fecha_fin, ambas incluidas (máximo MAX_DIAS_PERSONALIZADO días,
 *    no anteriores a FECHA_MINIMA ni posteriores a hoy).
 *
 * Período anterior (duración equivalente):
 *  - este_mes: los mismos días del mes anterior (1 a N); si ese mes es más corto, termina en su último
 *    día (p. ej. 1–31 de marzo se compara con 1–28/29 de febrero).
 *  - mes_anterior: el mes calendario completo anterior (28 a 31 días, según el mes).
 *  - este_anio: el mismo tramo del año anterior (1 de enero al mismo día y mes); el 29 de febrero se
 *    compara hasta el 28 de febrero del año anterior.
 *  - ultimos_3_meses y personalizado: el mismo número de días inmediatamente anterior, sin hueco.
 */
final class PeriodoReporte
{
    public const ZONA = 'America/La_Paz';

    public const TIPOS = [
        'este_mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
        'ultimos_3_meses' => 'Últimos 3 meses',
        'este_anio' => 'Este año',
        'personalizado' => 'Personalizado',
    ];

    /** Duración máxima del período personalizado: un año completo, incluso bisiesto. */
    public const MAX_DIAS_PERSONALIZADO = 366;

    /** Fecha mínima admitida en un período personalizado. */
    public const FECHA_MINIMA = '2020-01-01';

    private function __construct(
        public readonly string $tipo,
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $finExclusivo,
    ) {}

    /**
     * @param  string|null  $desde  fecha Y-m-d (solo personalizado)
     * @param  string|null  $hasta  fecha Y-m-d (solo personalizado)
     */
    public static function crear(string $tipo, ?string $desde = null, ?string $hasta = null, ?CarbonInterface $ahora = null): self
    {
        $hoy = CarbonImmutable::instance($ahora ?? CarbonImmutable::now())->setTimezone(self::ZONA)->startOfDay();

        [$inicio, $ultimoDia] = match ($tipo) {
            'este_mes' => [$hoy->startOfMonth(), $hoy],
            'mes_anterior' => [$hoy->subMonthNoOverflow()->startOfMonth(), $hoy->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'ultimos_3_meses' => [$hoy->subMonthsNoOverflow(3)->addDay(), $hoy],
            'este_anio' => [$hoy->startOfYear(), $hoy],
            'personalizado' => self::fechasPersonalizadas($desde, $hasta, $hoy),
            default => throw new InvalidArgumentException("Período no válido: {$tipo}."),
        };

        return new self($tipo, $inicio, $ultimoDia->addDay());
    }

    /** Período inmediatamente anterior, de duración equivalente (ver la documentación de la clase). */
    public function anterior(): self
    {
        $dias = $this->dias();

        [$inicio, $ultimoDia] = match ($this->tipo) {
            'este_mes' => (function () use ($dias) {
                $inicio = $this->inicio->subMonthNoOverflow()->startOfMonth();

                return [$inicio, $inicio->addDays(min($dias, $inicio->daysInMonth) - 1)];
            })(),
            'mes_anterior' => [$this->inicio->subMonthNoOverflow()->startOfMonth(), $this->inicio->subDay()],
            'este_anio' => [$this->inicio->subYearNoOverflow(), $this->ultimoDia()->subYearNoOverflow()],
            default => [$this->inicio->subDays($dias), $this->inicio->subDay()],
        };

        return new self($this->tipo, $inicio, $ultimoDia->addDay());
    }

    /** Último día incluido (00:00 de ese día en La Paz). */
    public function ultimoDia(): CarbonImmutable
    {
        return $this->finExclusivo->subDay();
    }

    public function dias(): int
    {
        return (int) round($this->inicio->diffInDays($this->finExclusivo));
    }

    public function etiqueta(): string
    {
        return self::TIPOS[$this->tipo].' ('.$this->rango().')';
    }

    /** «1 al 14 de octubre de 2026», «15 de julio al 14 de octubre de 2026», «1 de dic. de 2025 al …». */
    public function rango(): string
    {
        $fin = $this->ultimoDia();

        return match (true) {
            $this->inicio->isSameDay($fin) => $fin->translatedFormat('j \d\e F \d\e Y'),
            $this->inicio->isSameMonth($fin) => $this->inicio->format('j').' al '.$fin->translatedFormat('j \d\e F \d\e Y'),
            $this->inicio->isSameYear($fin) => $this->inicio->translatedFormat('j \d\e F').' al '.$fin->translatedFormat('j \d\e F \d\e Y'),
            default => $this->inicio->translatedFormat('j \d\e F \d\e Y').' al '.$fin->translatedFormat('j \d\e F \d\e Y'),
        };
    }

    /** Fechas Y-m-d del primer y último día (para formularios y enlaces). */
    public function fechas(): array
    {
        return ['fecha_inicio' => $this->inicio->toDateString(), 'fecha_fin' => $this->ultimoDia()->toDateString()];
    }

    /**
     * Filtra una columna TIMESTAMPTZ por el intervalo [inicio, finExclusivo). Los límites se envían con
     * su desplazamiento (-04:00), por lo que no dependen de la zona de la sesión de PostgreSQL.
     * No usar con columnas TIMESTAMP sin zona (p. ej. auditorias.created_at, guardada en UTC).
     */
    public function aplicar(Builder $consulta, string $columna): Builder
    {
        return $consulta
            ->where($columna, '>=', $this->inicio->format('Y-m-d H:i:sP'))
            ->where($columna, '<', $this->finExclusivo->format('Y-m-d H:i:sP'));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function fechasPersonalizadas(?string $desde, ?string $hasta, CarbonImmutable $hoy): array
    {
        $inicio = self::fecha($desde, 'fecha_inicio');
        $fin = self::fecha($hasta, 'fecha_fin');

        if ($inicio->greaterThan($fin)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
        }
        if ($inicio->lessThan(CarbonImmutable::parse(self::FECHA_MINIMA, self::ZONA)) || $fin->greaterThan($hoy)) {
            throw new InvalidArgumentException('Las fechas deben estar entre '.self::FECHA_MINIMA.' y hoy.');
        }
        if ((int) round($inicio->diffInDays($fin)) + 1 > self::MAX_DIAS_PERSONALIZADO) {
            throw new InvalidArgumentException('El período personalizado no puede superar '.self::MAX_DIAS_PERSONALIZADO.' días.');
        }

        return [$inicio, $fin];
    }

    private static function fecha(?string $valor, string $campo): CarbonImmutable
    {
        $fecha = $valor === null ? false : CarbonImmutable::createFromFormat('!Y-m-d', $valor, self::ZONA);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException("{$campo} debe ser una fecha con formato AAAA-MM-DD.");
        }

        return $fecha;
    }
}
