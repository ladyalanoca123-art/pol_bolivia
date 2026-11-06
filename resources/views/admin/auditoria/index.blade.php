@use('App\Support\PresentadorAuditoria', 'P')
{{--
    Auditoría: quién hizo qué y cuándo, en lenguaje claro (App\Support\PresentadorAuditoria).
    Datos: App\Http\Controllers\Admin\AuditoriaController. created_at ya llega en la zona de la aplicación.
--}}
@php
    $hayFiltros = collect($filtros)->filter(fn ($valor) => filled($valor))->isNotEmpty();
    $hoy = now()->toDateString();
    $ayer = now()->subDay()->toDateString();
    $titulosDia = fn ($fecha) => match ($fecha->toDateString()) {
        $hoy => 'Hoy',
        $ayer => 'Ayer',
        default => \Illuminate\Support\Str::ucfirst($fecha->translatedFormat('l j \d\e F \d\e Y')),
    };
    $porDia = $auditorias->getCollection()->groupBy(fn ($a) => $a->created_at?->toDateString());
    $objetos = P::objetos($auditorias->getCollection());

    // Un ícono de color por acción: creó (verde), cambió (azul), eliminó (rojo).
    $iconos = [
        'creado' => ['bg-emerald-100 text-emerald-700', 'M12 5v14M5 12h14'],
        'actualizado' => ['bg-blue-100 text-blue-800', 'M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Zm9.5-13.5 3 3'],
        'eliminado' => ['bg-red-100 text-red-700', 'M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3'],
    ];

    // Pestañas de período: usan el filtro «desde» (sin «hasta»).
    $desdeActual = $filtros['desde'] ?? null;
    $hastaActual = $filtros['hasta'] ?? null;
    $periodos = [
        'Hoy' => $hoy,
        'Últimos 7 días' => now()->subDays(6)->toDateString(),
        'Últimos 30 días' => now()->subDays(29)->toDateString(),
        'Todo' => null,
    ];
    $periodoActivo = $hastaActual ? null : array_search($desdeActual, $periodos, true);
    $fechasAMano = $hastaActual || ($desdeActual && $periodoActivo === false) || $errors->has('desde') || $errors->has('hasta');
@endphp
<x-admin.pagina titulo="Auditoría" seccion="Seguridad y trazabilidad"
    :contador="$auditorias->total().($auditorias->total() === 1 ? ' movimiento' : ' movimientos')"
    descripcion="Registro de todo lo que se crea, cambia o elimina en el sistema: quién lo hizo, cuándo y qué cambió.">

    {{-- Filtros en una sola fila (sin recuadros de resumen: el total va junto al título y cada día lleva su contador). --}}
    <form method="GET" action="{{ route('admin.auditoria.index') }}" aria-label="Filtrar auditoría" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="min-w-0 flex-1">
                <label for="modelo" class="text-[13px] font-semibold text-slate-600">Módulo</label>
                <select id="modelo" name="modelo" class="admin-campo !mt-1 !min-h-10">
                    <option value="">Todos</option>
                    @foreach ($modelos as $modelo)
                        <option value="{{ $modelo }}" @selected(($filtros['modelo'] ?? null) === $modelo)>{{ P::modulo($modelo) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 flex-1">
                <label for="accion" class="text-[13px] font-semibold text-slate-600">Acción</label>
                <select id="accion" name="accion" class="admin-campo !mt-1 !min-h-10">
                    <option value="">Todas</option>
                    @foreach (P::ACCIONES as $clave => [$nombre])
                        <option value="{{ $clave }}" @selected(($filtros['accion'] ?? null) === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 flex-1">
                <label for="usuario" class="text-[13px] font-semibold text-slate-600">Persona</label>
                <select id="usuario" name="usuario" class="admin-campo !mt-1 !min-h-10">
                    <option value="">Todas</option>
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected((int) ($filtros['usuario'] ?? 0) === $usuario->id)>{{ $usuario->nombres }} {{ $usuario->apellidos }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <x-admin.boton tamano="chico">Buscar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.auditoria.index') }}" class="admin-boton-borde !min-h-9 !px-3">Quitar filtros</a>@endif
            </div>
        </div>

        {{-- Fechas exactas, plegadas: casi siempre bastan los botones de período. --}}
        <div class="mt-2 flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
            <details class="group" @if ($fechasAMano) open @endif>
                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded text-[13px] font-semibold text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    Elegir fechas
                    <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                </summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="desde" class="text-[13px] font-semibold text-slate-600">Desde</label>
                        <input id="desde" name="desde" type="date" value="{{ $desdeActual ?? '' }}" max="{{ $hoy }}" class="admin-campo !mt-1 !min-h-10">
                        <x-input-error class="mt-1" :messages="$errors->get('desde')" />
                    </div>
                    <div>
                        <label for="hasta" class="text-[13px] font-semibold text-slate-600">Hasta</label>
                        <input id="hasta" name="hasta" type="date" value="{{ $hastaActual ?? '' }}" max="{{ $hoy }}" class="admin-campo !mt-1 !min-h-10">
                        <x-input-error class="mt-1" :messages="$errors->get('hasta')" />
                    </div>
                </div>
            </details>
            @if ($hayFiltros)<p class="text-[13px] text-slate-500">{{ $auditorias->total() }} {{ $auditorias->total() === 1 ? 'registro' : 'registros' }} con estos filtros</p>@endif
        </div>
    </form>

    {{-- Período: debajo de los filtros (usa el filtro «desde», sin «hasta»). --}}
    @php
        $iconosPeriodo = ['Hoy' => \App\Support\IconosPestanas::HOY, 'Últimos 7 días' => \App\Support\IconosPestanas::VARIOS_MESES, 'Últimos 30 días' => \App\Support\IconosPestanas::MES_ANTERIOR, 'Todo' => \App\Support\IconosPestanas::LISTA];
        $opcionesPeriodo = collect($periodos)->map(fn ($desde, $texto) => [$texto, route('admin.auditoria.index', array_filter([...collect($filtros)->except(['desde', 'hasta'])->all(), 'desde' => $desde])), $periodoActivo === $texto, $iconosPeriodo[$texto]])->values()->all();
    @endphp
    <x-admin.pestanas etiqueta="Período" :opciones="$opcionesPeriodo" />

    {{-- Línea de tiempo por día --}}
    @if ($auditorias->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio :titulo="$hayFiltros ? 'No hay movimientos con estos filtros' : 'Todavía no hay movimientos registrados'"
                :texto="$hayFiltros ? 'Prueba con otro módulo, otra persona u otras fechas.' : 'Cada vez que alguien cree, cambie o elimine información, aparecerá aquí.'">
                @if ($hayFiltros)<a href="{{ route('admin.auditoria.index') }}" class="admin-boton-borde">Ver todos los movimientos</a>@endif
            </x-admin.vacio>
        </x-admin.tarjeta>
    @else
        @foreach ($porDia as $dia => $registros)
            <section aria-labelledby="dia-{{ $dia }}">
                <h2 id="dia-{{ $dia }}" class="mb-2 flex items-center gap-2 text-base font-semibold text-blue-950">
                    {{ $titulosDia($registros->first()->created_at) }}
                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $registros->count() }}</span>
                </h2>
                <ol class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
                    @foreach ($registros as $auditoria)
                        @php
                            [$colorIcono, $trazo] = $iconos[$auditoria->accion] ?? ['bg-slate-100 text-slate-600', 'M12 8v4m0 4h.01'];
                            $objeto = $objetos[$auditoria->modelo.':'.$auditoria->modelo_id] ?? null;
                            $enlace = P::enlace($auditoria);
                            $estado = P::cambioDeEstado($auditoria);
                            $cambios = P::cambios($auditoria);
                            $verbo = P::ACCIONES[$auditoria->accion][1] ?? $auditoria->accion;
                        @endphp
                        <li class="flex gap-3 px-4 py-3.5 sm:gap-4 sm:px-5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $colorIcono }}">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $trazo }}" /></svg>
                                <span class="sr-only">{{ P::ACCIONES[$auditoria->accion][0] ?? $auditoria->accion }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                {{-- Frase: «Lady Alanoca cambió el seguimiento de Michi» (o la frase general si no hay nombre). --}}
                                <p class="text-[15px] leading-snug text-slate-900">
                                    <span class="font-semibold">{{ P::actor($auditoria) }}</span>
                                    @if ($objeto)
                                        {{ $verbo }} {{ $objeto[0] }}
                                        @if ($objeto[3])<a href="{{ $objeto[3] }}" class="font-semibold text-blue-800 hover:text-blue-950 hover:underline">{{ $objeto[1] }}</a>@else<span class="font-semibold">{{ $objeto[1] }}</span>@endif
                                        {{ $objeto[2] }}
                                    @else
                                        {{ P::accion($auditoria) }}
                                        @if ($enlace)
                                            <a href="{{ $enlace }}" class="font-semibold text-blue-800 hover:text-blue-950 hover:underline">#{{ $auditoria->modelo_id }}</a>
                                        @endif
                                    @endif
                                </p>

                                @if ($estado)
                                    <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[13px]">
                                        <span class="sr-only">Estado:</span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 font-semibold text-slate-700">{{ $estado[0] }}</span>
                                        <span aria-hidden="true" class="text-slate-400">→</span><span class="sr-only">a</span>
                                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 font-semibold text-blue-950">{{ $estado[1] }}</span>
                                    </p>
                                @endif

                                {{-- Línea gris: módulo · hora · ver qué cambió --}}
                                <div class="mt-1.5 text-[13px] text-slate-500">
                                    {{ P::modulo($auditoria->modelo) }}
                                    <span aria-hidden="true">·</span>
                                    <time datetime="{{ $auditoria->created_at?->toIso8601String() }}" title="{{ $auditoria->created_at?->format('d/m/Y H:i') }}">{{ $auditoria->created_at?->format('H:i') }}</time>
                                    @if ($cambios)
                                        <span aria-hidden="true">·</span>
                                        <details class="group inline open:mt-1 open:block">
                                            <summary class="inline cursor-pointer list-none font-semibold text-blue-800 hover:text-blue-950 hover:underline">
                                                <span class="group-open:hidden">{{ $auditoria->accion === 'actualizado' ? 'Ver qué cambió' : 'Ver datos' }} ({{ count($cambios) }}) ▾</span>
                                                <span class="hidden group-open:inline">Ocultar ▴</span>
                                            </summary>
                                            <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                                                <table class="min-w-full text-left text-[13px]">
                                                    <thead class="bg-slate-50 text-slate-500">
                                                        <tr>
                                                            <th scope="col" class="px-3 py-2 font-semibold">Campo</th>
                                                            @if ($auditoria->accion === 'actualizado')<th scope="col" class="px-3 py-2 font-semibold">Antes</th>@endif
                                                            <th scope="col" class="px-3 py-2 font-semibold">{{ $auditoria->accion === 'actualizado' ? 'Después' : ($auditoria->accion === 'eliminado' ? 'Valor eliminado' : 'Valor') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        @foreach ($cambios as [$nombreCampo, $antes, $despues])
                                                            <tr class="align-top">
                                                                <th scope="row" class="whitespace-nowrap px-3 py-2 font-medium text-slate-700">{{ $nombreCampo }}</th>
                                                                @if ($auditoria->accion === 'actualizado')<td class="px-3 py-2 text-slate-500 line-through decoration-slate-300">{{ $antes ?? '—' }}</td>@endif
                                                                <td class="px-3 py-2 text-slate-900">{{ $auditoria->accion === 'eliminado' ? $antes : $despues }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @if ($auditoria->ip)<p class="mt-1 text-xs text-slate-400">IP {{ $auditoria->ip }}</p>@endif
                                        </details>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
        @if ($auditorias->hasPages())<div>{{ $auditorias->links() }}</div>@endif
    @endif
</x-admin.pagina>
