@use('App\Support\CuestionarioVoluntariado', 'V')
@props(['postulacion'])

{{--
    Respuestas del cuestionario de voluntariado V1. Lo usan el ciudadano y el administrador.
    Una postulación sin version_cuestionario es del formulario anterior: sus datos NO se
    muestran como respuestas V1 (las 10 preguntas quedan en "—") y se presentan aparte,
    bajo "Datos de la postulación anterior", sin modificarlos.
--}}
@php
    $p = $postulacion;
    $esV1 = filled($p->version_cuestionario);
    $sinDato = '—';

    $filas = [
        'Motivación y experiencia' => [
            [1, '¿Por qué quiere ser voluntario/a?', $p->motivacion],
            [2, '¿Tiene experiencia previa?', $p->experiencia_previa === null ? null : ($p->experiencia_previa ? 'Sí' : 'No')],
            [3, 'Descripción de la experiencia', $p->experiencia_detalle],
        ],
        'Áreas y disponibilidad' => [
            [4, 'Áreas de interés', V::etiquetas('areas_interes', $p->areas_interes)],
            [5, 'Días disponibles', V::etiquetas('dias_disponibles', $p->dias_disponibles)],
            [6, 'Franjas horarias', V::etiquetas('franjas_horarias', $p->franjas_horarias)],
            [7, 'Zona de preferencia', $p->zona_preferencia],
        ],
        'Compromiso' => [
            [8, 'Habilidades útiles', $p->habilidades],
            [9, 'Mayor de 18 años', $p->confirma_mayor_edad ? 'Confirmado' : null],
            [10, 'Declaración de veracidad', $p->declara_informacion_veraz ? 'Aceptada' : null],
        ],
    ];

    // Datos del formulario anterior (solo para postulaciones sin versión del cuestionario).
    $anteriores = $esV1 ? [] : array_filter([
        'Motivación' => $p->motivacion,
        'Habilidades' => $p->habilidades,
        'Disponibilidad' => $p->disponibilidad,
        'Zona de preferencia' => $p->zona_preferencia,
    ], fn ($valor) => filled($valor));
@endphp

<div {{ $attributes }}>
    <div data-seccion="cuestionario-v1">
        @foreach ($filas as $seccion => $preguntas)
            <h4 class="mt-4 text-xs font-bold uppercase tracking-wider text-gray-500">{{ $seccion }}</h4>
            <dl class="mt-2 divide-y divide-gray-100 border-t border-gray-100 text-sm">
                @foreach ($preguntas as [$num, $texto, $valor])
                    @php
                        if (! $esV1) {
                            $valor = null; // Formulario anterior: ninguna respuesta V1 registrada.
                        } elseif ($num === 3 && ! $p->experiencia_previa) {
                            continue; // Pregunta condicional que no aplicó.
                        } elseif ($num === 8 && blank($valor)) {
                            $valor = 'No indicó (opcional)';
                        }
                    @endphp
                    <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2">
                        <dt class="text-gray-500"><span class="font-semibold text-blue-800">{{ $num }}.</span> {{ $texto }}</dt>
                        <dd class="whitespace-pre-line text-gray-900">{{ filled($valor) ? $valor : $sinDato }}</dd>
                    </div>
                @endforeach
            </dl>
        @endforeach
    </div>

    @if ($anteriores)
        <div data-seccion="postulacion-anterior" class="mt-6 rounded-md border border-gray-200 bg-gray-50 p-4">
            <h4 class="text-sm font-semibold text-gray-900">Datos de la postulación anterior</h4>
            <p class="text-xs text-gray-500">Registrados con el formulario previo al cuestionario actual. Se muestran tal como se guardaron.</p>
            <dl class="mt-2 divide-y divide-gray-200 text-sm">
                @foreach ($anteriores as $etiqueta => $valor)
                    <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2">
                        <dt class="text-gray-500">{{ $etiqueta }}</dt>
                        <dd class="whitespace-pre-line text-gray-900">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</div>
