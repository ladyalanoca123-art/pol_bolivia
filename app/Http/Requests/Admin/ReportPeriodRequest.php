<?php

namespace App\Http\Requests\Admin;

use App\Support\PeriodoReporte;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Período de consulta de Reportes (App\Support\PeriodoReporte).
 *
 * - periodo: este_mes, mes_anterior, ultimos_3_meses, este_anio o personalizado.
 * - fecha_inicio / fecha_fin (AAAA-MM-DD): obligatorias en personalizado; en los demás períodos se ignoran.
 * - Compatibilidad: si solo llegan fechas (formulario anterior), el período es personalizado.
 * - Sin parámetros no hay período (la vista actual muestra los totales sin filtro).
 */
class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El acceso lo controla la ruta (auth, verified, role:administrador).
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('periodo') && ($this->filled('fecha_inicio') || $this->filled('fecha_fin'))) {
            $this->merge(['periodo' => 'personalizado']);
        }
    }

    public function rules(): array
    {
        $hoy = CarbonImmutable::now(PeriodoReporte::ZONA)->toDateString();
        $fecha = ['date_format:Y-m-d', 'after_or_equal:'.PeriodoReporte::FECHA_MINIMA, 'before_or_equal:'.$hoy];

        return [
            'periodo' => ['nullable', 'string', Rule::in(array_keys(PeriodoReporte::TIPOS))],
            'fecha_inicio' => ['nullable', 'required_if:periodo,personalizado', ...$fecha],
            'fecha_fin' => ['nullable', 'required_if:periodo,personalizado', ...$fecha],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('periodo') !== 'personalizado' || $validator->errors()->hasAny(['fecha_inicio', 'fecha_fin'])) {
                return;
            }

            $inicio = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('fecha_inicio'), PeriodoReporte::ZONA);
            $fin = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('fecha_fin'), PeriodoReporte::ZONA);

            if ($inicio->greaterThan($fin)) {
                $validator->errors()->add('fecha_inicio', 'La fecha inicial no puede ser posterior a la fecha final.');
            } elseif ((int) round($inicio->diffInDays($fin)) + 1 > PeriodoReporte::MAX_DIAS_PERSONALIZADO) {
                $validator->errors()->add('fecha_fin', 'El período personalizado no puede superar '.PeriodoReporte::MAX_DIAS_PERSONALIZADO.' días.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'periodo.in' => 'Selecciona un período válido.',
            'fecha_inicio.required_if' => 'Indica la fecha inicial del período personalizado.',
            'fecha_fin.required_if' => 'Indica la fecha final del período personalizado.',
            'fecha_inicio.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_fin.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_inicio.after_or_equal' => 'La fecha inicial no puede ser anterior al '.PeriodoReporte::FECHA_MINIMA.'.',
            'fecha_fin.after_or_equal' => 'La fecha final no puede ser anterior al '.PeriodoReporte::FECHA_MINIMA.'.',
            'fecha_inicio.before_or_equal' => 'La fecha inicial no puede ser posterior a hoy.',
            'fecha_fin.before_or_equal' => 'La fecha final no puede ser posterior a hoy.',
        ];
    }

    /** Período validado, o null si no se pidió ninguno. */
    public function periodoReporte(): ?PeriodoReporte
    {
        $datos = $this->validated();

        return empty($datos['periodo'])
            ? null
            : PeriodoReporte::crear($datos['periodo'], $datos['fecha_inicio'] ?? null, $datos['fecha_fin'] ?? null);
    }
}
