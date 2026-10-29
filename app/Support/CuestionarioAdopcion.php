<?php

namespace App\Support;

/**
 * Cuestionario de solicitud de adopción, versión A2 corregida (26 preguntas).
 *
 * Única fuente de las preguntas y opciones: la usan la validación
 * (StoreSolicitudAdopcionRequest), el formulario del ciudadano y la vista
 * del administrador. En la base se guardan las claves; en pantalla, las etiquetas.
 */
final class CuestionarioAdopcion
{
    // a2-v3: teléfono de contacto obligatorio y consentimiento de contacto en lugar de la visita previa al hogar.
    public const VERSION = 'a2-v3';

    public const OPCIONES = [
        'tipo_vivienda' => ['casa' => 'Casa', 'departamento' => 'Departamento', 'cuarto' => 'Cuarto', 'compartida' => 'Vivienda compartida', 'otro' => 'Otro'],
        'tenencia' => ['propia' => 'Propia', 'alquilada' => 'Alquilada', 'anticretico' => 'En anticrético', 'familiar' => 'De un familiar', 'compartida' => 'Compartida con otra persona', 'otro' => 'Otro'],
        'permiso_propietario' => ['si' => 'Sí', 'no' => 'No', 'no_se' => 'No lo sé'],
        'permanencia' => ['dentro' => 'Dentro de la vivienda', 'exterior' => 'Patio o jardín', 'dentro_fuera' => 'Dentro y fuera de la vivienda', 'otro' => 'Otro'],
        'cerramiento' => ['si' => 'Sí', 'no' => 'No', 'no_seguro' => 'No estoy seguro/a', 'no_corresponde' => 'No corresponde'],
        'acuerdo_hogar' => ['si' => 'Sí', 'no' => 'No', 'no_hablado' => 'Aún no lo hemos hablado'],
        'otras_mascotas' => ['si' => 'Sí', 'no' => 'No'],
        'especies' => ['perros' => 'Perros', 'gatos' => 'Gatos', 'otros' => 'Otros'],
        'estado_grupo' => ['todas' => 'Todas', 'algunas' => 'Algunas', 'ninguna' => 'Ninguna', 'no_se' => 'No lo sé'],
        'mascotas_anteriores' => [
            'siguen' => 'Actualmente siguen conmigo',
            'fallecieron' => 'Fallecieron por edad o enfermedad',
            'entregadas' => 'Fueron entregadas a otra persona o familia',
            'perdidas' => 'Se perdieron o escaparon y no fueron recuperadas',
            'retiradas' => 'Fueron retiradas por otra persona o institución',
            'nunca' => 'Nunca tuve mascotas',
            'otro' => 'Otro',
        ],
        'responsable' => ['yo' => 'Yo', 'pareja' => 'Mi pareja', 'otro_adulto' => 'Otro adulto del hogar', 'compartida' => 'Compartiremos la responsabilidad entre varias personas', 'otra_persona' => 'Otra persona'],
        'apoyo' => ['si' => 'Sí', 'no' => 'No', 'depende' => 'Depende del día'],
        'tiempo_sola' => ['menos_2' => 'Menos de 2 horas', '2_4' => '2 a 4 horas', '4_6' => '4 a 6 horas', '6_8' => '6 a 8 horas', 'mas_8' => 'Más de 8 horas', 'depende' => 'Depende del día'],
        'motivo' => ['compania' => 'Compañía', 'rescate' => 'Darle un hogar a un animal rescatado', 'familia' => 'Incorporar una mascota a mi familia', 'otro' => 'Otro'],
        'compromiso_vacunas' => ['si' => 'Sí', 'no' => 'No'],
        'si_no_puede' => [
            'fundacion' => 'Me comunicaría primero con la fundación',
            'hogar_informando' => 'Buscaría un hogar responsable e informaría a la fundación',
            'persona_confianza' => 'La dejaría temporalmente con un familiar o persona de confianza',
            'por_su_cuenta' => 'Buscaría otra solución por mi cuenta',
            'otro' => 'Otro',
        ],
        'horarios_visita' => ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche', 'fin_semana' => 'Fin de semana', 'otro' => 'Otro'],
    ];

    /** Respuestas de la pregunta 12 que muestran la 13 y generan alerta. */
    public const ANTERIORES_CON_DETALLE = ['entregadas', 'perdidas', 'retiradas', 'otro'];

    /** Respuestas de la pregunta 4 que muestran la 5. */
    public const PERMANENCIA_CON_EXTERIOR = ['exterior', 'dentro_fuera'];

    // La clave acepta_visita_previa se conserva para las solicitudes anteriores (a2-v2 y previas, cuyo texto era
    // «Acepto una visita previa al hogar…»); desde a2-v3 es el consentimiento de contacto por WhatsApp o llamada.
    public const CONSENTIMIENTOS = [
        'acepta_visita_previa' => 'Acepto que POL-Bolivia me contacte por WhatsApp o llamada para evaluar mi solicitud y coordinar la entrega de la mascota.',
        'acepta_seguimiento' => 'Acepto las visitas o contactos de seguimiento posteriores a la adopción.',
        'no_ceder' => 'Me comprometo a no vender, regalar ni ceder la mascota a terceros sin comunicarme previamente con la fundación.',
        'declara_informacion_veraz' => 'Declaro que la información proporcionada es verdadera y acepto que pueda ser verificada durante el proceso de adopción.',
    ];

    /**
     * Preguntas por sección, para mostrar las respuestas al administrador y al ciudadano.
     * Cada pregunta: [número, campo, texto, catálogo de opciones o null].
     */
    public const SECCIONES = [
        'Vivienda' => [
            [1, 'tipo_vivienda', '¿Qué tipo de vivienda tiene?', 'tipo_vivienda'],
            [2, 'tenencia', 'La vivienda es', 'tenencia'],
            [3, 'permiso_propietario', '¿El propietario o responsable permite tener mascotas?', 'permiso_propietario'],
            [4, 'permanencia', '¿Dónde permanecerá principalmente la mascota?', 'permanencia'],
            [5, 'cerramiento', '¿El espacio exterior cuenta con un cerramiento seguro?', 'cerramiento'],
        ],
        'Hogar' => [
            [6, 'adultos', 'Adultos que viven en el hogar', null],
            [7, 'ninos', 'Niños que viven en el hogar', null],
            [8, 'edades_ninos', 'Edades de los niños', null],
            [9, 'acuerdo_hogar', '¿Todos los miembros del hogar están de acuerdo?', 'acuerdo_hogar'],
            [10, 'otras_mascotas', '¿Tiene otras mascotas actualmente?', 'otras_mascotas'],
            [11, 'mascotas_actuales', 'Información sobre sus mascotas actuales', null],
        ],
        'Experiencia' => [
            [12, 'mascotas_anteriores', '¿Qué ocurrió con las mascotas que tuvo anteriormente?', 'mascotas_anteriores'],
            [13, 'que_ocurrio', 'Qué ocurrió', null],
        ],
        'Rutina y cuidados' => [
            [14, 'responsable', 'Responsable principal de la mascota', 'responsable'],
            [15, 'responsable_otro', 'Quién será el responsable', null],
            [16, 'apoyo', '¿Habrá otra persona que pueda atenderla cuando no esté?', 'apoyo'],
            [17, 'tiempo_sola', 'Tiempo que permanecería sola en un día habitual', 'tiempo_sola'],
        ],
        'Compromiso' => [
            [18, 'motivo', 'Motivo principal de la adopción', 'motivo'],
            [19, 'compromiso_vacunas', '¿Se compromete a mantener vacunas y desparasitación al día?', 'compromiso_vacunas'],
            [20, 'si_no_puede', 'Si ya no pudiera cuidarla, ¿qué haría?', 'si_no_puede'],
        ],
        'Visita' => [
            [22, 'horarios_visita', 'Horarios disponibles para las visitas de seguimiento', 'horarios_visita'],
        ],
    ];

    public static function claves(string $catalogo): array
    {
        return array_keys(self::OPCIONES[$catalogo]);
    }

    public static function etiqueta(string $catalogo, ?string $clave): ?string
    {
        return $clave === null ? null : (self::OPCIONES[$catalogo][$clave] ?? $clave);
    }

    /**
     * Texto legible de una respuesta guardada, o null si la pregunta no aplicó.
     */
    public static function respuestaLegible(array $respuestas, string $campo, ?string $catalogo): ?string
    {
        if (! array_key_exists($campo, $respuestas) || $respuestas[$campo] === null || $respuestas[$campo] === '') {
            return null;
        }

        $valor = $respuestas[$campo];

        if ($campo === 'mascotas_actuales') {
            $especies = collect($valor['especies'] ?? [])->map(fn ($e) => self::etiqueta('especies', $e))->join(', ');

            return sprintf(
                '%s · %s · Vacunas al día: %s · Esterilizadas: %s',
                $valor['cantidad'].((int) $valor['cantidad'] === 1 ? ' mascota' : ' mascotas'),
                $especies,
                self::etiqueta('estado_grupo', $valor['vacunas'] ?? null),
                self::etiqueta('estado_grupo', $valor['esterilizadas'] ?? null),
            );
        }

        if (is_array($valor)) {
            return collect($valor)->map(fn ($v) => self::etiqueta($catalogo, $v))->join(', ');
        }

        $texto = $catalogo ? self::etiqueta($catalogo, (string) $valor) : (string) $valor;

        // "Otro" con detalle opcional (preguntas 18 y 20).
        $detalle = $respuestas[$campo.'_otro'] ?? null;
        if ($valor === 'otro' && in_array($campo, ['motivo', 'si_no_puede'], true) && filled($detalle)) {
            $texto .= ': '.$detalle;
        }

        return $texto;
    }
}
