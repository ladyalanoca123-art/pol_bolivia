<?php

namespace Tests\Support;

use App\Support\CuestionarioVoluntariado;

/**
 * Datos válidos del cuestionario de voluntariado V1 para las pruebas.
 */
final class CuestionarioVoluntariadoDatos
{
    /** Lo que envía el formulario del ciudadano. */
    public static function valido(array $cambios = []): array
    {
        return array_replace([
            'motivacion' => 'Quiero ayudar a los animales.',
            'experiencia_previa' => 'no',
            'areas_interes' => ['paseos', 'ferias_adopcion'],
            'dias_disponibles' => ['sabado', 'domingo'],
            'franjas_horarias' => ['manana', 'tarde'],
            'zona_preferencia' => 'Centro',
            'habilidades' => 'Primeros auxilios',
            'confirma_mayor_edad' => '1',
            'declara_informacion_veraz' => '1',
        ], $cambios);
    }

    /** Atributos para crear una PostulacionVoluntariado directamente en la base. */
    public static function registro(array $cambios = []): array
    {
        return array_replace([
            'motivacion' => 'Quiero ayudar a los animales.',
            'experiencia_previa' => false,
            'areas_interes' => ['paseos', 'ferias_adopcion'],
            'dias_disponibles' => ['sabado', 'domingo'],
            'franjas_horarias' => ['manana', 'tarde'],
            'disponibilidad' => 'Sábado, Domingo · Mañana, Tarde',
            'zona_preferencia' => 'Centro',
            'habilidades' => 'Primeros auxilios',
            'confirma_mayor_edad' => true,
            'declara_informacion_veraz' => true,
            'version_cuestionario' => CuestionarioVoluntariado::VERSION,
        ], $cambios);
    }
}
