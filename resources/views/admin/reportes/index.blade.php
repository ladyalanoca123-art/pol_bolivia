{{--
    Reportes: «¿cuántos hay?». Pestañas por categoría (App\Http\Controllers\Admin\ReporteController::CATEGORIAS) y
    período (App\Support\PeriodoReporte). La vista solo presenta: no consulta la base ni calcula indicadores.
    Los pendientes por atender están en el Panel principal y el historial de cambios en Auditoría.
--}}
@php
    $conPeriodo = $periodoReporte !== null;
    $i = $indicadoresPeriodo;
    $numero = fn ($valor, int $decimales = 1) => number_format((float) $valor, $decimales, ',', '.');
    $dias = fn (?float $valor) => $valor === null ? null : $numero($valor).' '.((float) $valor === 1.0 ? 'día' : 'días');
    $maxEvolucion = max(1, ...array_map(fn ($m) => max($m['solicitudes'], $m['adopciones']), $evolucionMensual));
    $hayEvolucion = collect($evolucionMensual)->contains(fn ($m) => $m['solicitudes'] + $m['adopciones'] > 0);
    $especies = ['perro' => 'Perros', 'gato' => 'Gatos'];
    $tamanos = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'];
    $maxDisponibles = max(1, ...array_values($disponibles['especie']), ...array_values($disponibles['tamano']));
    $edad = fn ($m) => $m->edad.' '.($m->edad_en === 'meses' ? ($m->edad === 1 ? 'mes' : 'meses') : ($m->edad === 1 ? 'año' : 'años'));
    $tarjeta = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    $enlace = 'inline-flex items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400';
    $vacio = 'text-sm text-slate-500';
    $personalizadoAbierto = $periodoReporte?->tipo === 'personalizado' || $errors->hasAny(['fecha_inicio', 'fecha_fin']);
    $fechasPersonalizadas = $periodoReporte?->tipo === 'personalizado' ? $periodoReporte->fechas() : ['fecha_inicio' => '', 'fecha_fin' => ''];
    // La categoría se conserva al cambiar de período y el período al cambiar de categoría.
    $conCategoria = fn (array $parametros) => array_filter([...$parametros, 'categoria' => $categoria === 'general' ? null : $categoria]);
    $iconosCategoria = [
        'mascotas' => 'M12 20.25s-7.5-4.2-7.5-10.1A4.15 4.15 0 0 1 12 7.6a4.15 4.15 0 0 1 7.5 2.55c0 5.9-7.5 10.1-7.5 10.1Z',
        'solicitudes' => 'M9 12h6M9 16h6M8.25 3.75h7.5l3.75 3.75v12a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75V4.5a.75.75 0 0 1 .75-.75h3Z',
        'seguimientos' => 'M8.25 3v2.25M15.75 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5ZM9 14.25l2.25 2.25L15 12.75',
        'voluntariado' => 'M18 18.7a9.1 9.1 0 0 0 3.7-.5 3 3 0 0 0-4.7-2.7M18 18.7a6 6 0 0 0-12 0M12 9.75a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6 0a2.25 2.25 0 1 0 0-4.5M6 9.75a2.25 2.25 0 1 1 0-4.5',
        'usuarios' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0',
    ];
@endphp
<x-admin.pagina titulo="Reportes" seccion="Reportes y estadísticas"
    :descripcion="$conPeriodo
        ? ucfirst($periodoReporte->rango()).' · comparado con el período anterior ('.$periodoAnterior->rango().')'
        : 'Totales acumulados y estado actual. Elige un período para compararlo con el anterior.'">
    {{-- Descargas, como al principio: arriba a la derecha y separadas del período. Bajan el reporte completo del período elegido. --}}
    <x-slot name="acciones">
                <a href="{{ route('admin.reportes.pdf', $parametrosExportacion) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2" title="Reporte completo de todas las categorías">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="m9 14 3 3 3-3"/></svg>
                    Descargar PDF
                </a>
                <a href="{{ route('admin.reportes.excel', $parametrosExportacion) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2" title="Reporte completo de todas las categorías">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 3v18"/></svg>
                    Descargar Excel
                </a>
    </x-slot>

    {{-- Período: botones cuadrados que llenan el ancho; «Personalizado» abre las fechas debajo. --}}
    @php
        $iconosPeriodo = ['este_mes' => \App\Support\IconosPestanas::HOY, 'mes_anterior' => \App\Support\IconosPestanas::MES_ANTERIOR, 'ultimos_3_meses' => \App\Support\IconosPestanas::VARIOS_MESES, 'este_anio' => \App\Support\IconosPestanas::ANIO];
        $opcionesPeriodo = collect(\App\Support\PeriodoReporte::TIPOS)->except('personalizado')
            ->map(fn ($nombre, $clave) => [$nombre, route('admin.reportes.index', $conCategoria(['periodo' => $clave])), $periodoReporte?->tipo === $clave, $iconosPeriodo[$clave] ?? \App\Support\IconosPestanas::HOY])
            ->values()->all();
        $esPersonalizado = $periodoReporte?->tipo === 'personalizado';
    @endphp
    <section aria-labelledby="titulo-periodo" class="{{ $tarjeta }} p-3 sm:p-4" x-data="{ personalizado: {{ $personalizadoAbierto ? 'true' : 'false' }} }">
        <div class="mb-2 flex items-center justify-between gap-3">
            <h2 id="titulo-periodo" class="text-sm font-semibold text-slate-700">Período</h2>
            @if ($conPeriodo)
                <a href="{{ route('admin.reportes.index', $conCategoria([])) }}" class="{{ $enlace }} text-[13px]">Quitar período</a>
            @endif
        </div>
        <x-admin.pestanas etiqueta="Períodos" actual="true" :opciones="$opcionesPeriodo">
            <button type="button" x-on:click="personalizado = ! personalizado" x-bind:aria-expanded="personalizado.toString()" aria-controls="periodo-personalizado"
                    class="admin-pestana {{ $esPersonalizado ? 'admin-pestana-activa' : '' }}">
                <svg class="h-[18px] w-[18px] shrink-0 {{ $esPersonalizado ? 'text-amber-300' : 'text-blue-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ \App\Support\IconosPestanas::AJUSTES }}" /></svg>
                <span class="truncate">Personalizado</span>
            </button>
        </x-admin.pestanas>

        <form id="periodo-personalizado" method="GET" action="{{ route('admin.reportes.index') }}" x-show="personalizado" @unless ($personalizadoAbierto) x-cloak @endunless
              class="mt-3 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:flex-row sm:flex-wrap sm:items-end">
            <input type="hidden" name="periodo" value="personalizado">
            @if ($categoria !== 'general')<input type="hidden" name="categoria" value="{{ $categoria }}">@endif
            <div>
                <label for="fecha_inicio" class="admin-etiqueta">Fecha inicial</label>
                <input id="fecha_inicio" type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $fechasPersonalizadas['fecha_inicio']) }}" min="{{ \App\Support\PeriodoReporte::FECHA_MINIMA }}" max="{{ now()->toDateString() }}" required
                       class="admin-campo sm:w-auto" @error('fecha_inicio') aria-invalid="true" aria-describedby="error-fecha-inicio" @enderror>
                @error('fecha_inicio')<p id="error-fecha-inicio" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="fecha_fin" class="admin-etiqueta">Fecha final</label>
                <input id="fecha_fin" type="date" name="fecha_fin" value="{{ old('fecha_fin', $fechasPersonalizadas['fecha_fin']) }}" min="{{ \App\Support\PeriodoReporte::FECHA_MINIMA }}" max="{{ now()->toDateString() }}" required
                       class="admin-campo sm:w-auto" @error('fecha_fin') aria-invalid="true" aria-describedby="error-fecha-fin" @enderror>
                @error('fecha_fin')<p id="error-fecha-fin" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <x-admin.boton>Aplicar</x-admin.boton>
            <p class="text-xs text-slate-500 sm:basis-full">Hasta {{ \App\Support\PeriodoReporte::MAX_DIAS_PERSONALIZADO }} días, entre el {{ \Illuminate\Support\Carbon::parse(\App\Support\PeriodoReporte::FECHA_MINIMA)->translatedFormat('j \d\e F \d\e Y') }} y hoy.</p>
        </form>
        @error('periodo')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </section>

    {{-- Categorías: debajo del período --}}
    @php
        $iconosBarra = ['general' => \App\Support\IconosPestanas::RESUMEN, 'mascotas' => \App\Support\IconosPestanas::MASCOTAS, 'solicitudes' => \App\Support\IconosPestanas::SOLICITUDES, 'seguimientos' => \App\Support\IconosPestanas::SEGUIMIENTOS, 'voluntariado' => \App\Support\IconosPestanas::CORAZON, 'usuarios' => \App\Support\IconosPestanas::PERSONA];
        $opcionesCategoria = collect($categorias)
            ->map(fn ($nombre, $clave) => [$nombre, route('admin.reportes.index', array_filter([...$parametrosExportacion, 'categoria' => $clave === 'general' ? null : $clave])), $categoria === $clave, $iconosBarra[$clave]])
            ->values()->all();
    @endphp
    <x-admin.pestanas etiqueta="Categorías del reporte" :opciones="$opcionesCategoria" />


    @if ($categoria === 'general')
        {{--
            1 · Estado actual: la lista de cada categoría, a la vista. Cada fila lleva un punto de color según lo que
            significa: verde = bien o terminado, ámbar = en proceso, rojo = pendiente o rechazado, azul/morado = en curso
            o rol, gris = dato general.
        --}}
        @php
            $puntos = ['verde' => 'bg-emerald-500', 'ambar' => 'bg-amber-400', 'rojo' => 'bg-red-500', 'azul' => 'bg-blue-500', 'indigo' => 'bg-indigo-600', 'gris' => 'bg-slate-300'];
            $listasEstado = [
                ['mascotas', 'Mascotas', [
                    ['Total mascotas', $totalMascotas, 'gris'], ['Mascotas disponibles', $mascotasPorEstado['disponible'], 'verde'],
                    ['Mascotas reservadas', $mascotasPorEstado['reservada'], 'ambar'], ['Mascotas adoptadas', $mascotasPorEstado['adoptada'], 'azul'],
                    ['Perros', $mascotasPorEspecie['perro'], 'gris'], ['Gatos', $mascotasPorEspecie['gato'], 'gris'],
                ]],
                ['solicitudes', 'Solicitudes', [
                    ['Pendientes', $solicitudesPorEstado['pendiente'], 'ambar'], ['En evaluación', $solicitudesPorEstado['en_espera'], 'azul'],
                    ['Reservadas (entrega pendiente)', $solicitudesPorEstado['aprobada'], 'indigo'], ['Adopciones realizadas', $solicitudesPorEstado['aceptada'], 'verde'],
                    ['Rechazadas', $solicitudesPorEstado['rechazada'], 'rojo'], ['Entregas no concretadas', $solicitudesPorEstado['no_concretada'], 'rojo'],
                    ['Canceladas', $solicitudesPorEstado['cancelada'], 'gris'], ['Cerradas al adoptarse la mascota', $solicitudesPorEstado['cerrada'], 'gris'],
                ]],
                ['seguimientos', 'Seguimientos', [
                    ['Sin asignar', $seguimientosPorEstado['sin_asignar'], 'ambar'], ['Activos', $seguimientosPorEstado['activo'], 'azul'], ['Cerrados', $seguimientosPorEstado['cerrado'], 'gris'],
                    ['Visitas programadas', $visitasPorEstado['programada'], 'azul'], ['Visitas realizadas', $visitasPorEstado['realizada'], 'verde'], ['Visitas sin asistencia', $visitasPorEstado['no_asistio'], 'ambar'],
                ]],
                ['seguimientos', 'Incidencias', [
                    ['Pendientes', $incidenciasPorEstado['pendiente'], 'rojo'], ['En proceso', $incidenciasPorEstado['en_proceso'], 'ambar'], ['Atendidas', $incidenciasPorEstado['atendida'], 'verde'],
                ]],
                ['voluntariado', 'Voluntariado', [
                    ['Voluntarios activos', $voluntariosPorEstado['activo'], 'verde'], ['Voluntarios inactivos', $voluntariosPorEstado['inactivo'], 'gris'],
                    ['Postulaciones pendientes', $postulacionesPorEstado['pendiente'], 'ambar'], ['Postulaciones aprobadas', $postulacionesPorEstado['aprobada'], 'verde'],
                    ['Postulaciones rechazadas', $postulacionesPorEstado['rechazada'], 'rojo'], ['Postulaciones canceladas', $postulacionesPorEstado['cancelada'], 'gris'],
                ]],
                ['usuarios', 'Usuarios', [
                    ['Total de cuentas', $totalUsuarios, 'gris'], ['Administradores', $usuariosPorRol['administrador'], 'indigo'],
                    ['Voluntarios', $usuariosPorRol['voluntario'], 'ambar'], ['Ciudadanos', $usuariosPorRol['ciudadano'], 'verde'],
                    ['Cuentas activas', $usuariosActivos, 'verde'], ['Cuentas desactivadas', $totalUsuarios - $usuariosActivos, 'rojo'],
                ]],
            ];
        @endphp
        @php
            // Color del borde de cada fila del resumen (el de la categoría).
            $bordesCategoria = ['mascotas' => 'border-s-emerald-500', 'solicitudes' => 'border-s-amber-400', 'seguimientos' => 'border-s-blue-500', 'voluntariado' => 'border-s-rose-400', 'usuarios' => 'border-s-indigo-600'];
        @endphp
        <section aria-labelledby="titulo-estado" class="space-y-2">
            <div class="flex flex-wrap items-baseline gap-x-3">
                <h2 id="titulo-estado" class="text-lg font-semibold text-blue-950">Estado actual</h2>
                <p class="text-[13px] text-slate-500">Cantidades a hoy, sin filtro de período · toca una fila para ver su lista</p>
            </div>
            {{-- Una fila por categoría, como las listas del panel: título, sus datos básicos en línea y una flecha. --}}
            <ul class="space-y-2">
                @foreach ($listasEstado as [$clave, $grupo, $filas])
                    <li>
                        <a href="{{ route('admin.reportes.index', array_filter([...$parametrosExportacion, 'categoria' => $clave])) }}"
                           class="group flex items-center gap-4 rounded-xl border border-s-[6px] border-slate-200 {{ $bordesCategoria[$clave] ?? 'border-s-slate-300' }} bg-white px-4 py-3 shadow-sm transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:px-5">
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 text-base font-semibold text-blue-950">
                                    <svg class="h-5 w-5 shrink-0 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconosBarra[$clave] }}" /></svg>
                                    {{ $grupo }}
                                </p>
                                <dl class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[13px]">
                                    @foreach ($filas as [$etiqueta, $valor, $tono])
                                        <div class="flex items-center gap-1.5">
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $puntos[$tono] ?? $puntos['gris'] }}" aria-hidden="true"></span>
                                            <dt class="text-slate-600">{{ $etiqueta }}</dt>
                                            <dd class="font-semibold {{ $valor > 0 ? 'text-blue-950' : 'text-slate-400' }}">{{ $valor }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-300 text-blue-950 transition group-hover:border-blue-900 group-hover:bg-blue-50" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6" /></svg>
                            </span>
                            <span class="sr-only">Ver la lista de {{ $grupo }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- 2 · Qué pasó en el período --}}
        <section aria-labelledby="titulo-que-paso" class="space-y-4">
            <h2 id="titulo-que-paso" class="text-lg font-semibold text-blue-950">Qué pasó en el período</h2>
            @if ($conPeriodo)
                @include('admin.reportes.partials.indicadores-periodo')
            @endif
            @include('admin.reportes.partials.evolucion')
        </section>

        {{-- Mascotas disponibles por especie y tamaño, plegado (su detalle completo está en la pestaña Mascotas). --}}
        <details class="group {{ $tarjeta }} p-4 sm:p-5">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                <span class="text-base font-semibold text-blue-950">Mascotas que esperan hogar</span>
                <span class="shrink-0 text-sm font-semibold text-blue-800"><span class="group-open:hidden">Ver ▾</span><span class="hidden group-open:inline">Ocultar ▴</span></span>
            </summary>
            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @include('admin.reportes.partials.disponibles')
                @include('admin.reportes.partials.espera')
            </div>
        </details>

    @else
        @include('admin.reportes.partials.lista-categoria')
    @endif

    <p class="pb-4 text-xs text-slate-500">Generado el {{ now()->format('d/m/Y H:i') }} (hora de Bolivia).</p>
</x-admin.pagina>
