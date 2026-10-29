<?php

namespace App\Support;

/**
 * Cuestionario de postulación de voluntariado, versión V1 (10 preguntas).
 *
 * Única fuente de las opciones: la usan la validación, el formulario del ciudadano,
 * las vistas y el filtro del administrador. En la base se guardan las claves.
 */
final class CuestionarioVoluntariado
{
    public const VERSION = 'v1';

    public const OPCIONES = [
        'experiencia_previa' => ['si' => 'Sí', 'no' => 'No'],
        'areas_interes' => [
            'rescate' => 'Rescate',
            'cuidado_limpieza' => 'Cuidado y limpieza',
            'paseos' => 'Paseos',
            'transporte' => 'Transporte',
            'visitas_seguimiento' => 'Visitas de seguimiento',
            'ferias_adopcion' => 'Ferias de adopción',
            'difusion_redes' => 'Difusión en redes',
        ],
        'dias_disponibles' => [
            'lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles', 'jueves' => 'Jueves',
            'viernes' => 'Viernes', 'sabado' => 'Sábado', 'domingo' => 'Domingo',
        ],
        'franjas_horarias' => ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'],
    ];

    public static function claves(string $catalogo): array
    {
        return array_keys(self::OPCIONES[$catalogo]);
    }

    /** Etiquetas legibles de una lista de claves, separadas por coma. */
    public static function etiquetas(string $catalogo, ?array $claves): string
    {
        return collect($claves ?? [])
            ->map(fn (string $clave) => self::OPCIONES[$catalogo][$clave] ?? $clave)
            ->join(', ');
    }

    /**
     * Texto de disponibilidad que se guarda en la columna existente "disponibilidad"
     * (máx. 150) y que PostgreSQL copia al perfil del voluntario al aprobar.
     * Ej.: "Sábado, Domingo · Mañana, Tarde".
     */
    public static function disponibilidadTexto(array $dias, array $franjas): string
    {
        // Ordena según el catálogo para que el texto sea siempre igual.
        $ordenar = fn (string $catalogo, array $claves) => array_values(array_intersect(self::claves($catalogo), $claves));

        return self::etiquetas('dias_disponibles', $ordenar('dias_disponibles', $dias))
            .' · '.self::etiquetas('franjas_horarias', $ordenar('franjas_horarias', $franjas));
    }
}
