@props(['de', 'valor'])
{{--
    Estado con su color, igual en todo el panel. de: solicitud, mascota, seguimiento, visita, incidencia, postulacion,
    voluntario o usuario. <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
--}}
@php
    $estados = [
        'solicitud' => [
            'pendiente' => 'ambar', 'en_espera' => 'azul', 'aprobada' => 'indigo', 'aceptada' => 'verde',
            'rechazada' => 'rojo', 'no_concretada' => 'rojo', 'cancelada' => 'gris', 'cerrada' => 'gris',
        ],
        'mascota' => ['disponible' => 'verde', 'reservada' => 'ambar', 'adoptada' => 'azul'],
        'seguimiento' => ['sin_asignar' => 'ambar', 'activo' => 'azul', 'cerrado' => 'gris'],
        'visita' => ['programada' => 'azul', 'realizada' => 'verde', 'no_asistio' => 'ambar'],
        'incidencia' => ['pendiente' => 'rojo', 'en_proceso' => 'ambar', 'atendida' => 'verde'],
        'postulacion' => ['pendiente' => 'ambar', 'aprobada' => 'verde', 'rechazada' => 'rojo', 'cancelada' => 'gris'],
        'voluntario' => ['activo' => 'verde', 'inactivo' => 'gris'],
        'usuario' => [1 => 'verde', 0 => 'gris'],
    ];
    $textos = [
        'solicitud' => \App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES,
        'mascota' => \App\Models\Mascota::ESTADOS_LEGIBLES,
        'seguimiento' => ['sin_asignar' => 'Sin asignar', 'activo' => 'Activo', 'cerrado' => 'Cerrado'],
        'visita' => ['programada' => 'Programada', 'realizada' => 'Realizada', 'no_asistio' => 'No asistió'],
        'incidencia' => ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'atendida' => 'Atendida'],
        'postulacion' => ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'cancelada' => 'Cancelada'],
        'voluntario' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
        'usuario' => [1 => 'Activo', 0 => 'Inactivo'],
    ];
    $clave = is_bool($valor) ? (int) $valor : $valor;
@endphp
<x-admin.insignia :tono="$estados[$de][$clave] ?? 'gris'" {{ $attributes }}>{{ $textos[$de][$clave] ?? \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', (string) $valor)) }}</x-admin.insignia>
