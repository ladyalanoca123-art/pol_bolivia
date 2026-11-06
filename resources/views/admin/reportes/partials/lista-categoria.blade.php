{{--
    Lista de consulta de una categoría de Reportes: una fila por registro con sus datos básicos, el borde del color de
    su estado o rol y un «Ver» que abre su ficha. Solo para mirar: las acciones están en la lista de cada módulo.
    $registros: paginación del controlador (filtrada por período); $desglose: [estado o rol => cantidad] de esa lista.
--}}
@php
    $estados = [
        'mascotas' => ['disponible' => ['Disponible', 'verde'], 'reservada' => ['Reservada', 'ambar'], 'adoptada' => ['Adoptada', 'azul']],
        'solicitudes' => [
            'pendiente' => ['Recibida', 'ambar'], 'en_espera' => ['En evaluación', 'azul'], 'aprobada' => ['Reservada', 'indigo'], 'aceptada' => ['Adopción realizada', 'verde'],
            'rechazada' => ['Rechazada', 'rojo'], 'no_concretada' => ['Entrega no concretada', 'rojo'], 'cancelada' => ['Cancelada', 'gris'], 'cerrada' => ['Cerrada', 'gris'],
        ],
        'seguimientos' => ['sin_asignar' => ['Sin asignar', 'ambar'], 'activo' => ['Activo', 'azul'], 'cerrado' => ['Cerrado', 'gris']],
        'voluntariado' => ['pendiente' => ['Por revisar', 'ambar'], 'aprobada' => ['Aprobada', 'verde'], 'rechazada' => ['Rechazada', 'rojo'], 'cancelada' => ['Cancelada', 'gris']],
        'usuarios' => ['administrador' => ['Administrador', 'indigo'], 'voluntario' => ['Voluntario', 'ambar'], 'ciudadano' => ['Ciudadano', 'verde']],
    ][$categoria];
    // Plurales para la línea de totales («2 activos»); los que no cambian no hace falta escribirlos.
    $plurales = [
        'mascotas' => ['disponible' => 'disponibles', 'reservada' => 'reservadas', 'adoptada' => 'adoptadas'],
        'solicitudes' => ['pendiente' => 'recibidas', 'aprobada' => 'reservadas', 'aceptada' => 'adopciones realizadas', 'rechazada' => 'rechazadas', 'no_concretada' => 'entregas no concretadas', 'cancelada' => 'canceladas', 'cerrada' => 'cerradas'],
        'seguimientos' => ['activo' => 'activos', 'cerrado' => 'cerrados'],
        'voluntariado' => ['aprobada' => 'aprobadas', 'rechazada' => 'rechazadas', 'cancelada' => 'canceladas'],
        'usuarios' => ['administrador' => 'administradores', 'voluntario' => 'voluntarios', 'ciudadano' => 'ciudadanos'],
    ][$categoria];
    $bordes = ['verde' => 'border-s-emerald-500', 'ambar' => 'border-s-amber-400', 'rojo' => 'border-s-red-500', 'azul' => 'border-s-blue-500', 'indigo' => 'border-s-indigo-600', 'gris' => 'border-s-slate-300'];
    $chips = ['verde' => 'bg-emerald-100 text-emerald-800', 'ambar' => 'bg-amber-100 text-amber-900', 'rojo' => 'bg-red-100 text-red-800', 'azul' => 'bg-blue-100 text-blue-950', 'indigo' => 'bg-indigo-100 text-indigo-900', 'gris' => 'bg-slate-100 text-slate-700'];
    $chip = 'whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-semibold';
    $roles = ['administrador' => ['Administrador', 'indigo'], 'voluntario' => ['Voluntario', 'ambar'], 'ciudadano' => ['Ciudadano', 'verde']];
    $sexos = ['macho' => 'macho', 'hembra' => 'hembra'];
    $nombrePersona = fn ($u) => $u ? trim($u->nombres.' '.$u->apellidos) : '—';
    $totalLista = $registros->total();
@endphp

<section aria-labelledby="titulo-lista" class="space-y-2">
    {{-- Línea de totales de esta misma lista --}}
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h2 id="titulo-lista" class="text-lg font-semibold text-blue-950">{{ $categorias[$categoria] }}</h2>
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-slate-600">
            <span class="font-semibold text-blue-950">{{ $totalLista }} {{ $conPeriodo ? 'en el período' : 'en total' }}</span>
            @foreach ($estados as $clave => [$texto, $tono])
                @if (($desglose[$clave] ?? 0) > 0)
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ str_replace('border-s-', 'bg-', $bordes[$tono]) }}" aria-hidden="true"></span>{{ $desglose[$clave] }} {{ $desglose[$clave] === 1 ? mb_strtolower($texto) : ($plurales[$clave] ?? mb_strtolower($texto)) }}</span>
                @endif
            @endforeach
        </p>
    </div>

    @if ($registros->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio :titulo="$conPeriodo ? 'No hay registros en este período.' : 'Todavía no hay registros.'" :texto="$conPeriodo ? 'Prueba con otro período o quítalo para ver todo.' : 'Aparecerán aquí a medida que se registren.'" />
        </x-admin.tarjeta>
    @else
        <ul class="space-y-2">
            @foreach ($registros as $r)
                @php
                    // [título, línea de datos, [texto, tono] del estado o rol, enlace a la ficha, datos extra en etiquetas]
                    [$titulo, $linea, [$estadoTexto, $tono], $enlaceFicha, $extras] = match ($categoria) {
                        'mascotas' => [
                            $r->nombre,
                            ($r->especie === 'gato' ? 'Gato' : 'Perro').' · '.($sexos[$r->sexo] ?? $r->sexo).' · '.$r->edad_formateada.' · registrada '.$r->created_at->diffForHumans(),
                            $estados[$r->estado] ?? [ucfirst($r->estado), 'gris'],
                            route('admin.mascotas.show', $r), [],
                        ],
                        'solicitudes' => [
                            $nombrePersona($r->solicitante),
                            'Quiere adoptar a '.($r->mascota?->nombre ?? '—').' · '.$r->created_at->diffForHumans(),
                            $estados[$r->estado] ?? [ucfirst($r->estado), 'gris'],
                            route('admin.solicitudes-adopcion.show', $r),
                            array_filter([\App\Models\SolicitudAdopcion::NIVELES_ALERTA[$r->nivel_alerta] ?? null]),
                        ],
                        'seguimientos' => [
                            $r->solicitud?->mascota?->nombre ?? '—',
                            'Familia: '.$nombrePersona($r->solicitud?->solicitante).' · Voluntario: '.($r->voluntario ? $nombrePersona($r->voluntario) : 'sin asignar'),
                            $estados[$r->estado] ?? [ucfirst($r->estado), 'gris'],
                            route('admin.seguimientos.show', $r),
                            array_filter([$r->visitas_count.' '.($r->visitas_count === 1 ? 'visita' : 'visitas'), $r->incidencias_pendientes_count > 0 ? $r->incidencias_pendientes_count.' '.($r->incidencias_pendientes_count === 1 ? 'incidencia pendiente' : 'incidencias pendientes') : null]),
                        ],
                        'voluntariado' => [
                            $nombrePersona($r->usuario),
                            (\App\Support\CuestionarioVoluntariado::etiquetas('areas_interes', $r->areas_interes) ?: 'Sin áreas indicadas').' · '.$r->created_at->diffForHumans(),
                            $estados[$r->estado] ?? [ucfirst($r->estado), 'gris'],
                            route('admin.postulaciones-voluntariado.show', $r),
                            array_filter([$r->estado === 'aprobada' && $r->voluntario ? ($r->voluntario->estado === 'activo' ? 'Voluntario activo' : 'Voluntario inactivo') : null]),
                        ],
                        'usuarios' => [
                            $nombrePersona($r),
                            $r->email.($r->telefono ? ' · '.$r->telefono : '').' · desde '.$r->created_at->format('d/m/Y'),
                            $roles[$r->rol] ?? [ucfirst($r->rol), 'gris'],
                            route('admin.usuarios.show', $r),
                            array_filter([$r->estado ? null : 'Inactivo']),
                        ],
                    };
                @endphp
                <li class="flex items-center gap-4 rounded-xl border border-s-[6px] border-slate-200 {{ $bordes[$tono] ?? 'border-s-slate-300' }} bg-white px-4 py-2.5 shadow-sm sm:px-5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-semibold text-blue-950">{{ $titulo }}</p>
                        <p class="truncate text-[13px] text-slate-600">{{ $linea }}</p>
                    </div>
                    <div class="hidden flex-wrap items-center justify-end gap-1.5 sm:flex">
                        <span class="{{ $chip }} {{ $chips[$tono] ?? $chips['gris'] }}">{{ $estadoTexto }}</span>
                        @foreach ($extras as $extra)
                            <span class="{{ $chip }} {{ $extra === 'Inactivo' || str_contains($extra, 'incidencia') || $extra === 'Condición incompatible' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700' }}">{{ $extra }}</span>
                        @endforeach
                    </div>
                    <a href="{{ $enlaceFicha }}" class="admin-boton-borde !min-h-9 shrink-0 !px-3">Ver<span class="sr-only"> {{ $titulo }}</span></a>
                </li>
            @endforeach
        </ul>
        @if ($registros->hasPages())<div>{{ $registros->links() }}</div>@endif
    @endif
</section>
