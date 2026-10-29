<?php

namespace App\Support;

use App\Models\VisitaSeguimiento;
use Illuminate\Validation\Rule;

final class CuestionarioVisitaSeguimiento
{
    public static function preguntas(int $numeroVisita): array
    {
        $sintomas = [
            'ninguno' => 'Ninguno',
            'herida' => 'Herida',
            'vomitos_diarrea' => 'Vómitos o diarrea',
            'respiracion' => 'Dificultad para respirar',
            'otro' => 'Otro',
        ];

        if ($numeroVisita === 1) {
            return [
                'apetito' => ['label' => '¿Cómo está comiendo?', 'options' => ['normal' => 'Normal', 'menos' => 'Menos de lo habitual', 'no_comiendo' => 'No está comiendo']],
                'animo' => ['label' => '¿Cómo está de ánimo y actividad?', 'options' => ['bien' => 'Activa y tranquila', 'adaptandose' => 'Adaptándose', 'decaida' => 'Decaída o inquieta']],
                'adaptacion' => ['label' => '¿Cómo se adapta al hogar?', 'options' => ['buena' => 'Bien', 'proceso' => 'En proceso', 'dificultades' => 'Tiene dificultades']],
                'convivencia' => ['label' => '¿Cómo convive con la familia u otros animales?', 'options' => ['buena' => 'Bien', 'gradual' => 'Requiere adaptación', 'dificultades' => 'Hay dificultades', 'no_aplica' => 'No aplica']],
                'sintomas' => ['label' => '¿Observaste algún problema de salud?', 'multiple' => true, 'options' => $sintomas],
            ];
        }

        return [
            'salud_general' => ['label' => 'Estado general de salud', 'options' => ['bueno' => 'Bueno', 'atencion' => 'Requiere atención', 'preocupante' => 'Preocupante']],
            'vacunas' => ['label' => 'Vacunas', 'options' => ['al_dia' => 'Al día', 'pendientes' => 'Pendientes', 'no_sabe' => 'No sabe']],
            'cuidados' => ['label' => '¿La familia mantiene los cuidados acordados?', 'options' => ['si' => 'Sí', 'parcialmente' => 'Parcialmente', 'no' => 'No']],
            'estabilidad' => ['label' => '¿La adopción parece estable?', 'options' => ['estable' => 'Sí', 'apoyo' => 'Necesita apoyo', 'problema' => 'Hay un problema']],
            'sintomas' => ['label' => '¿Observaste algún problema de salud?', 'multiple' => true, 'options' => $sintomas],
        ];
    }

    public static function reglas(int $numeroVisita): array
    {
        $reglas = [
            'fecha_realizada' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'respuestas' => ['required', 'array'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        foreach (self::preguntas($numeroVisita) as $campo => $pregunta) {
            if ($pregunta['multiple'] ?? false) {
                $reglas["respuestas.{$campo}"] = ['nullable', 'array', 'max:5'];
                $reglas["respuestas.{$campo}.*"] = ['string', Rule::in(array_keys($pregunta['options']))];
            } else {
                $reglas["respuestas.{$campo}"] = ['required', 'string', Rule::in(array_keys($pregunta['options']))];
            }
        }

        return $reglas;
    }

    public static function numero(VisitaSeguimiento $visita): int
    {
        return max(1, $visita->seguimiento()->firstOrFail()->visitas()->where('id', '<=', $visita->id)->count());
    }

    public static function resumen(int $numeroVisita, array $respuestas): string
    {
        $lineas = [];
        foreach (self::preguntas($numeroVisita) as $campo => $pregunta) {
            $seleccionadas = $respuestas[$campo] ?? [];
            $seleccionadas = is_array($seleccionadas) ? $seleccionadas : [$seleccionadas];
            $etiquetas = array_map(fn (string $valor): string => $pregunta['options'][$valor], $seleccionadas);
            $lineas[] = $pregunta['label'].': '.($etiquetas === [] ? 'Ninguno reportado' : implode(', ', $etiquetas));
        }

        return implode("\n", $lineas);
    }

    public static function estadoSalud(int $numeroVisita, array $respuestas): string
    {
        if ($numeroVisita === 2) {
            return self::preguntas(2)['salud_general']['options'][$respuestas['salud_general']];
        }

        $sintomas = $respuestas['sintomas'] ?? [];

        return array_diff($sintomas, ['ninguno']) === [] ? 'Sin alertas reportadas' : 'Señales reportadas';
    }
}
