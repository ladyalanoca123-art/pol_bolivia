<?php

namespace Tests\Support;

/**
 * Respuestas válidas del cuestionario de adopción A2 para las pruebas.
 * Caso base: casa propia, sin niños ni otras mascotas, sin antecedentes → sin alertas.
 */
final class CuestionarioAdopcionDatos
{
    /**
     * Atributos para crear una SolicitudAdopcion directamente en la base (sin pasar por el formulario).
     * Corresponde al caso base de valido(): nivel verde y sin alertas.
     */
    public static function registro(array $cambios = []): array
    {
        $respuestas = collect(self::valido())
            ->except(['zona_aproximada', 'acepta_visita_previa', 'acepta_seguimiento', 'no_ceder', 'declara_informacion_veraz'])
            ->all();
        $respuestas['consentimientos'] = array_fill_keys(['acepta_visita_previa', 'acepta_seguimiento', 'no_ceder', 'declara_informacion_veraz'], true);

        return array_replace([
            'tiene_otras_mascotas' => false,
            'viven_otras_personas' => true,
            'zona_aproximada' => 'Centro',
            'horario_preferido' => 'Tarde, Fin de semana',
            'declara_informacion_veraz' => true,
            'respuestas' => $respuestas,
            'version_cuestionario' => \App\Support\CuestionarioAdopcion::VERSION,
            'nivel_alerta' => 'verde',
            'alertas' => [],
            'version_reglas' => \App\Services\EvaluadorSolicitudAdopcion::VERSION_REGLAS,
        ], $cambios);
    }

    public static function valido(array $cambios = []): array
    {
        return array_replace([
            'tipo_vivienda' => 'casa',
            'tenencia' => 'propia',
            'permanencia' => 'dentro',
            'adultos' => 2,
            'ninos' => 0,
            'acuerdo_hogar' => 'si',
            'otras_mascotas' => 'no',
            'mascotas_anteriores' => 'nunca',
            'responsable' => 'yo',
            'apoyo' => 'si',
            'tiempo_sola' => '2_4',
            'motivo' => 'compania',
            'compromiso_vacunas' => 'si',
            'si_no_puede' => 'fundacion',
            'zona_aproximada' => 'Queru Queru, Cochabamba',
            'horarios_visita' => ['tarde', 'fin_semana'],
            'acepta_visita_previa' => '1',
            'acepta_seguimiento' => '1',
            'no_ceder' => '1',
            'declara_informacion_veraz' => '1',
        ], $cambios);
    }
}
