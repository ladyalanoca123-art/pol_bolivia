<?php

namespace App\Services;

use App\Support\CuestionarioAdopcion as C;

/**
 * Calcula las alertas de una solicitud de adopción a partir de sus respuestas.
 *
 * Las alertas ayudan al administrador a revisar: nunca aprueban ni rechazan.
 * Niveles: verde (sin observaciones), amarillo (requiere revisión),
 * rojo (condición incompatible). El nivel general es el más alto encontrado.
 */
class EvaluadorSolicitudAdopcion
{
    public const VERSION_REGLAS = 'a2-v2-r1';

    public const NIVELES = ['verde' => 0, 'amarillo' => 1, 'rojo' => 2];

    /**
     * @param  array<string, mixed>  $r  respuestas validadas del cuestionario
     * @return array{nivel: string, alertas: list<array{pregunta: int, nivel: string, mensaje: string}>}
     */
    public function evaluar(array $r): array
    {
        $alertas = [];
        $agregar = function (int $pregunta, string $nivel, string $mensaje) use (&$alertas): void {
            $alertas[] = compact('pregunta', 'nivel', 'mensaje');
        };

        // 3. Permiso del propietario
        match ($r['permiso_propietario'] ?? null) {
            'no' => $agregar(3, 'rojo', 'El propietario o responsable no permite mascotas.'),
            'no_se' => $agregar(3, 'amarillo', 'No sabe si el propietario permite mascotas: pedir confirmación.'),
            default => null,
        };

        // 5. Cerramiento del espacio exterior
        match ($r['cerramiento'] ?? null) {
            'no' => $agregar(5, 'amarillo', 'El espacio exterior no tiene cerramiento seguro: riesgo de escape.'),
            'no_seguro' => $agregar(5, 'amarillo', 'No está seguro/a del cerramiento: verificar en la visita.'),
            default => null,
        };

        // 9. Acuerdo del hogar
        match ($r['acuerdo_hogar'] ?? null) {
            'no' => $agregar(9, 'rojo', 'No todos los miembros del hogar están de acuerdo.'),
            'no_hablado' => $agregar(9, 'amarillo', 'El hogar aún no habló la adopción: conversar antes de continuar.'),
            default => null,
        };

        // 11. Vacunas de las mascotas actuales
        if (in_array($r['mascotas_actuales']['vacunas'] ?? null, ['algunas', 'ninguna', 'no_se'], true)) {
            $agregar(11, 'amarillo', 'Sus mascotas actuales no tienen todas las vacunas al día: riesgo de contagio.');
        }

        // 12. Antecedentes con mascotas anteriores
        if (in_array($r['mascotas_anteriores'] ?? null, C::ANTERIORES_CON_DETALLE, true)) {
            $etiqueta = C::etiqueta('mascotas_anteriores', $r['mascotas_anteriores']);
            $agregar(12, 'amarillo', "Antecedente con mascotas anteriores: «{$etiqueta}». Revisar el contexto.");
        }

        // 17. Tiempo sola
        if (($r['tiempo_sola'] ?? null) === 'mas_8') {
            $agregar(17, 'amarillo', 'La mascota estaría sola más de 8 horas al día.');
        }

        // 19. Compromiso con vacunas y desparasitación
        if (($r['compromiso_vacunas'] ?? null) === 'no') {
            $agregar(19, 'rojo', 'No se compromete a mantener vacunas y desparasitación.');
        }

        // 20. Si ya no pudiera cuidarla
        if (($r['si_no_puede'] ?? null) === 'por_su_cuenta') {
            $agregar(20, 'amarillo', 'Buscaría otra solución sin avisar a la fundación.');
        }

        // Rojas primero; dentro de cada nivel, por número de pregunta.
        usort($alertas, fn (array $a, array $b) => [self::NIVELES[$b['nivel']], $a['pregunta']]
            <=> [self::NIVELES[$a['nivel']], $b['pregunta']]);

        $nivel = 'verde';
        foreach ($alertas as $alerta) {
            if (self::NIVELES[$alerta['nivel']] > self::NIVELES[$nivel]) {
                $nivel = $alerta['nivel'];
            }
        }

        return ['nivel' => $nivel, 'alertas' => $alertas];
    }
}
