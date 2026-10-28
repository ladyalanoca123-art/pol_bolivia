@use('App\Support\PresentadorAuditoria', 'P')
<x-app-layout>
    {{--
        Auditoría: quién hizo qué y cuándo, en lenguaje claro (App\Support\PresentadorAuditoria).
        Datos: App\Http\Controllers\Admin\AuditoriaController. created_at ya llega en la zona de la aplicación.
    --}}
    @php
        $tarjeta = 'rounded-2xl border border-slate-200 bg-white';
        $campo = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-800 focus:ring-blue-800';
        $etiqueta = 'block text-xs font-semibold uppercase tracking-wide text-slate-500';
        $hayFiltros = collect($filtros)->filter(fn ($valor) => filled($valor))->isNotEmpty();
        // Clases completas (Tailwind solo genera las que aparecen escritas).
        $colores = [
            'amber' => ['bg-amber-100 text-amber-800', 'bg-amber-500'],
            'emerald' => ['bg-emerald-100 text-emerald-800', 'bg-emerald-500'],
            'sky' => ['bg-sky-100 text-sky-800', 'bg-sky-500'],
            'red' => ['bg-red-100 text-red-800', 'bg-red-500'],
            'violet' => ['bg-violet-100 text-violet-800', 'bg-violet-500'],
            'slate' => ['bg-slate-100 text-slate-700', 'bg-slate-400'],
        ];
        $acciones = [
            'creado' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'actualizado' => 'bg-blue-50 text-blue-800 ring-blue-200',
            'eliminado' => 'bg-red-50 text-red-700 ring-red-200',
        ];
        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();
        $titulosDia = fn ($fecha) => match ($fecha->toDateString()) {
            $hoy => 'Hoy',
            $ayer => 'Ayer',
            default => \Illuminate\Support\Str::ucfirst($fecha->translatedFormat('l j \d\e F \d\e Y')),
        };
        $porDia = $auditorias->getCollection()->groupBy(fn ($a) => $a->created_at?->toDateString());
        $iniciales = fn (string $nombre) => collect(preg_split('/\s+/', trim($nombre)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    @endphp

    <div class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

            {{-- Encabezado --}}
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Seguridad y trazabilidad</p>
                <h1 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl">Auditoría</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-600 sm:text-base">Registro de todo lo que se crea, cambia o elimina en el sistema: quién lo hizo, cuándo y qué cambió.</p>
            </header>

            {{-- Resumen --}}
            <section aria-labelledby="titulo-resumen">
                <h2 id="titulo-resumen" class="sr-only">Resumen de actividad</h2>
                <dl class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ([
                        ['Movimientos hoy', $resumen['hoy'], 'Desde las 00:00'],
                        ['Últimos 7 días', $resumen['semana'], 'Incluye hoy'],
                        ['Personas activas', $resumen['personas'], 'Hicieron cambios en 7 días'],
                        ['Eliminaciones', $resumen['eliminaciones'], 'En los últimos 7 días'],
                    ] as [$titulo, $valor, $ayuda])
                        <div class="{{ $tarjeta }} p-4 sm:p-5">
                            <dt class="text-sm font-medium text-slate-600">{{ $titulo }}</dt>
                            <dd class="mt-2 text-3xl font-bold leading-none {{ $titulo === 'Eliminaciones' && $valor > 0 ? 'text-red-700' : 'text-blue-950' }}">{{ $valor }}</dd>
                            <dd class="mt-2 text-xs text-slate-500">{{ $ayuda }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.auditoria.index') }}" class="{{ $tarjeta }} p-4 sm:p-5" aria-label="Filtrar auditoría">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label for="modelo" class="{{ $etiqueta }}">Módulo</label>
                        <select id="modelo" name="modelo" class="{{ $campo }}">
                            <option value="">Todos</option>
                            @foreach ($modelos as $modelo)
                                <option value="{{ $modelo }}" @selected(($filtros['modelo'] ?? null) === $modelo)>{{ P::modulo($modelo) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="accion" class="{{ $etiqueta }}">Acción</label>
                        <select id="accion" name="accion" class="{{ $campo }}">
                            <option value="">Todas</option>
                            @foreach (P::ACCIONES as $clave => [$nombre])
                                <option value="{{ $clave }}" @selected(($filtros['accion'] ?? null) === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="usuario" class="{{ $etiqueta }}">Persona</label>
                        <select id="usuario" name="usuario" class="{{ $campo }}">
                            <option value="">Todas</option>
                            @foreach ($usuarios as $usuario)
                                <option value="{{ $usuario->id }}" @selected((int) ($filtros['usuario'] ?? 0) === $usuario->id)>{{ $usuario->nombres }} {{ $usuario->apellidos }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="desde" class="{{ $etiqueta }}">Desde</label>
                        <input id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}" max="{{ $hoy }}" class="{{ $campo }}">
                        <x-input-error class="mt-1" :messages="$errors->get('desde')" />
                    </div>
                    <div>
                        <label for="hasta" class="{{ $etiqueta }}">Hasta</label>
                        <input id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}" max="{{ $hoy }}" class="{{ $campo }}">
                        <x-input-error class="mt-1" :messages="$errors->get('hasta')" />
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex min-h-10 items-center rounded-full bg-blue-950 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">Aplicar filtros</button>
                    @if ($hayFiltros)
                        <a href="{{ route('admin.auditoria.index') }}" class="text-sm font-semibold text-slate-600 hover:text-blue-950 hover:underline">Quitar filtros</a>
                    @endif
                    <p class="text-sm text-slate-500 sm:ms-auto">{{ $auditorias->total() }} {{ $auditorias->total() === 1 ? 'registro' : 'registros' }}{{ $hayFiltros ? ' con estos filtros' : '' }}</p>
                </div>
            </form>

            {{-- Línea de tiempo --}}
            @if ($auditorias->isEmpty())
                <div class="{{ $tarjeta }} px-6 py-14 text-center">
                    <p class="text-lg font-semibold text-blue-950">{{ $hayFiltros ? 'No hay movimientos con estos filtros' : 'Todavía no hay movimientos registrados' }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">{{ $hayFiltros ? 'Prueba con otro módulo, otra persona u otras fechas.' : 'Cada vez que alguien cree, cambie o elimine información, aparecerá aquí.' }}</p>
                    @if ($hayFiltros)
                        <a href="{{ route('admin.auditoria.index') }}" class="mt-5 inline-flex rounded-full border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver todos los movimientos</a>
                    @endif
                </div>
            @else
                @foreach ($porDia as $dia => $registros)
                    <section aria-labelledby="dia-{{ $dia }}">
                        <h2 id="dia-{{ $dia }}" class="mb-3 flex items-center gap-3 text-sm font-semibold text-slate-700">
                            {{ $titulosDia($registros->first()->created_at) }}
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $registros->count() }}</span>
                        </h2>
                        <ol class="{{ $tarjeta }} divide-y divide-slate-100">
                            @foreach ($registros as $auditoria)
                                @php
                                    $actor = P::actor($auditoria);
                                    [$chip, $punto] = $colores[P::color($auditoria->modelo)];
                                    $enlace = P::enlace($auditoria);
                                    $estado = P::cambioDeEstado($auditoria);
                                    $cambios = P::cambios($auditoria);
                                @endphp
                                <li class="flex gap-3 p-4 sm:gap-4 sm:p-5">
                                    <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $auditoria->usuario_id ? 'bg-blue-950 text-amber-300' : 'bg-slate-200 text-slate-600' }}">{{ $auditoria->usuario_id ? $iniciales($actor) : 'SIS' }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                            <p class="text-sm text-slate-900">
                                                <span class="font-semibold">{{ $actor }}</span>
                                                {{ P::accion($auditoria) }}
                                                @if ($enlace)
                                                    <a href="{{ $enlace }}" class="font-semibold text-blue-800 hover:text-blue-950 hover:underline">#{{ $auditoria->modelo_id }}</a>
                                                @else
                                                    <span class="text-slate-500">#{{ $auditoria->modelo_id }}</span>
                                                @endif
                                            </p>
                                            <time datetime="{{ $auditoria->created_at?->toIso8601String() }}" class="shrink-0 text-xs text-slate-500" title="{{ $auditoria->created_at?->format('d/m/Y H:i') }}">{{ $auditoria->created_at?->format('d/m/Y H:i') }}</time>
                                        </div>

                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-semibold {{ $chip }}"><span aria-hidden="true" class="h-1.5 w-1.5 rounded-full {{ $punto }}"></span>{{ P::modulo($auditoria->modelo) }}</span>
                                            <span class="rounded-full px-2.5 py-0.5 font-semibold ring-1 ring-inset {{ $acciones[$auditoria->accion] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">{{ P::ACCIONES[$auditoria->accion][0] ?? $auditoria->accion }}</span>
                                            @if ($estado)
                                                <span class="text-slate-600">{{ $estado[0] }} <span aria-hidden="true">→</span><span class="sr-only">a</span> <span class="font-semibold text-slate-900">{{ $estado[1] }}</span></span>
                                            @endif
                                        </div>

                                        @if ($cambios)
                                            <details class="group mt-3">
                                                <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-blue-800 hover:text-blue-950">
                                                    <span class="group-open:hidden">Ver detalle ({{ count($cambios) }} {{ count($cambios) === 1 ? 'campo' : 'campos' }})</span>
                                                    <span class="hidden group-open:inline">Ocultar detalle</span>
                                                </summary>
                                                <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                                                    <table class="min-w-full text-left text-xs">
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
                                                @if ($auditoria->ip)<p class="mt-2 text-xs text-slate-400">IP {{ $auditoria->ip }}</p>@endif
                                            </details>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach

                @if ($auditorias->hasPages())<div>{{ $auditorias->links() }}</div>@endif
            @endif
        </div>
    </div>
</x-app-layout>
