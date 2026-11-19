@php
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
    $estadoActual = $filters['estado'] ?? null;
    // Accesos rápidos a las etapas en trámite.
    $rapidos = ['' => 'Todas', 'hoy' => 'Hoy ('.$recibidasHoy.')', 'pendiente' => 'Recibidas', 'en_espera' => 'En evaluación', 'aprobada' => 'Reservadas', 'aceptada' => 'Concretadas', 'cerradas' => 'Cerradas'];
@endphp
<x-admin.pagina titulo="Solicitudes de adopción" :contador="$solicitudes->total().($solicitudes->total() === 1 ? ' solicitud' : ' solicitudes')" seccion="Adopciones" descripcion="Primero se muestran las solicitudes en trámite: las más aptas (sin observaciones) arriba y, en cada color, la más antigua primero. Las alertas ayudan a priorizar; no aprueban ni rechazan.">

    <x-admin.tarjeta>
        {{-- El estado se elige con los botones de abajo (se conserva al buscar). --}}
        <form method="GET" action="{{ route('admin.solicitudes-adopcion.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
            @if (filled($estadoActual))<input type="hidden" name="estado" value="{{ $estadoActual }}">@endif
            <div>
                <label for="buscar" class="admin-etiqueta">Buscar ciudadano o mascota</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre o apellido">
            </div>
            <div>
                <label for="nivel_alerta" class="admin-etiqueta">Revisión</label>
                <select id="nivel_alerta" name="nivel_alerta" class="admin-campo">
                    <option value="">Todas</option>
                    @foreach (\App\Models\SolicitudAdopcion::NIVELES_ALERTA as $nivel => $texto)
                        <option value="{{ $nivel }}" @selected(($filters['nivel_alerta'] ?? '') === $nivel)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <x-admin.boton>Buscar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.solicitudes-adopcion.index') }}" class="admin-boton-borde">Limpiar</a>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    {{-- Accesos rápidos por etapa: debajo de los filtros --}}
    @php
        $iconosEtapa = ['' => \App\Support\IconosPestanas::LISTA, 'hoy' => \App\Support\IconosPestanas::HOY, 'pendiente' => \App\Support\IconosPestanas::BANDEJA, 'en_espera' => \App\Support\IconosPestanas::LUPA, 'aprobada' => \App\Support\IconosPestanas::MARCADOR, 'aceptada' => \App\Support\IconosPestanas::CHECK, 'cerradas' => \App\Support\IconosPestanas::ARCHIVO];
        // Un estado cerrado concreto (p. ej. desde un enlace antiguo) marca el botón «Cerradas».
        $botonActual = in_array($estadoActual, \App\Models\SolicitudAdopcion::ESTADOS_CERRADOS, true) ? 'cerradas' : ($estadoActual ?? '');
        $opcionesEtapa = collect($rapidos)->map(fn ($texto, $clave) => [$texto, route('admin.solicitudes-adopcion.index', array_filter([...$filters, 'estado' => $clave ?: null])), $botonActual === (string) $clave, $iconosEtapa[$clave]])->values()->all();
    @endphp
    <x-admin.pestanas etiqueta="Filtrar por etapa" :opciones="$opcionesEtapa" />

    @if ($solicitudes->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio :titulo="$estadoActual === 'hoy' ? 'Hoy no llegaron solicitudes.' : 'No se encontraron solicitudes.'" :texto="$estadoActual === 'hoy' ? 'Las que lleguen hoy aparecerán aquí.' : ($hayFiltros ? 'Prueba con otro estado o búsqueda.' : 'Cuando un ciudadano envíe una solicitud, aparecerá aquí.')" />
        </x-admin.tarjeta>
    @else
        @php
            $enTramite = ['pendiente', 'en_espera', 'aprobada'];
            $pasos = ['pendiente' => 1, 'en_espera' => 2, 'aprobada' => 3];
            $bordes = ['rojo' => 'border-s-red-500', 'amarillo' => 'border-s-amber-400', 'verde' => 'border-s-emerald-500'];
            $coloresFinal = ['aceptada' => 'text-emerald-700', 'rechazada' => 'text-red-700', 'no_concretada' => 'text-red-700'];
            $chip = 'whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-semibold';
            $grupos = $solicitudes->getCollection()->groupBy(fn ($s) => in_array($s->estado, $enTramite, true) ? 'tramite' : 'finalizadas');
        @endphp

        {{-- En trámite: fila blanca con borde de color según la revisión, el paso y el botón de lo que toca hacer. --}}
        @if ($grupos->has('tramite'))
            <section aria-labelledby="titulo-tramite" class="space-y-2">
                <h2 id="titulo-tramite" class="text-base font-semibold text-blue-950">En trámite</h2>
                <ul class="space-y-2">
                    @foreach ($grupos['tramite'] as $solicitud)
                        @php $alertas = count($solicitud->alertas ?? []); @endphp
                        <li class="flex flex-col gap-3 rounded-xl border border-s-[6px] border-slate-200 {{ $bordes[$solicitud->nivel_alerta] ?? 'border-s-slate-300' }} bg-white px-4 py-3 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center sm:px-5">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="text-base font-semibold text-blue-950 hover:underline">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</a>
                                <p class="text-sm text-slate-600">
                                    Quiere adoptar a <span class="font-semibold text-slate-900">{{ $solicitud->mascota->nombre }}</span>
                                    <span aria-hidden="true">·</span>
                                    <time datetime="{{ $solicitud->created_at->toIso8601String() }}" title="Recibida el {{ $solicitud->created_at->format('d/m/Y H:i') }}">{{ $solicitud->created_at->diffForHumans() }}</time>
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <span class="{{ $chip }} {{ $solicitud->estado === 'pendiente' ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-950' }}">Paso {{ $pasos[$solicitud->estado] }} de 4 · {{ $solicitud->estadoLegible() }}</span>
                                    @if ($solicitud->nivel_alerta === 'rojo')
                                        <span class="{{ $chip }} bg-red-100 text-red-800">Condición incompatible</span>
                                    @elseif ($solicitud->nivel_alerta === 'amarillo')
                                        <span class="{{ $chip }} bg-amber-100 text-amber-900">{{ $alertas }} {{ $alertas === 1 ? 'respuesta a revisar' : 'respuestas a revisar' }}</span>
                                    @elseif ($solicitud->nivel_alerta === 'verde')
                                        <span class="{{ $chip }} bg-emerald-100 text-emerald-800">Sin observaciones</span>
                                    @endif
                                    @if ($solicitud->estado === 'aprobada' && ! $solicitud->compromiso_foto_path)
                                        <span class="{{ $chip }} bg-red-100 text-red-800">Falta la foto del acta</span>
                                    @endif
                                    @if (($enTramitePorMascota[$solicitud->mascota_id] ?? 0) > 1)
                                        <span class="{{ $chip }} bg-indigo-100 text-indigo-900">{{ $enTramitePorMascota[$solicitud->mascota_id] }} solicitudes por {{ $solicitud->mascota->nombre }}</span>
                                    @endif
                                </div>
                            </div>
                            @if ($solicitud->estado === 'aprobada')
                                <x-admin.boton variante="destacado" class="w-full sm:w-auto" :href="route('admin.solicitudes-adopcion.show', $solicitud)">Registrar entrega</x-admin.boton>
                            @else
                                <x-admin.boton class="w-full sm:w-auto" :href="route('admin.solicitudes-adopcion.show', $solicitud)">Revisar<span class="sr-only"> la solicitud de {{ $solicitud->solicitante->nombres }}</span></x-admin.boton>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @php
            [$concretadas, $cerradas] = ($grupos['finalizadas'] ?? collect())->partition(fn ($s) => $s->estado === 'aceptada');
            $seguimientoChip = [
                'activo' => ['bg-blue-100 text-blue-950', 'Seguimiento en curso'],
                'cerrado' => ['bg-emerald-100 text-emerald-800', 'Seguimiento concluido'],
                'sin_asignar' => ['bg-amber-100 text-amber-900', 'Falta asignar voluntario'],
            ];
        @endphp

        {{-- Adopciones concretadas: el resultado del sistema. Tarjetas con la foto de la mascota y su nueva familia. --}}
        @if ($concretadas->isNotEmpty())
            <section aria-labelledby="titulo-concretadas" class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="titulo-concretadas" class="flex items-center gap-2 text-base font-semibold text-emerald-800">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                        Adopciones concretadas
                    </h2>
                    @if (($filters['estado'] ?? null) === 'aceptada')
                        <p class="text-sm text-slate-600"><span class="font-bold text-emerald-800">{{ $solicitudes->total() }}</span> {{ $solicitudes->total() === 1 ? 'mascota ya tiene' : 'mascotas ya tienen' }} un hogar gracias a este proceso.</p>
                    @endif
                </div>
                <ul class="space-y-3">
                    @foreach ($concretadas as $solicitud)
                        @php
                            $foto = $solicitud->mascota->fotos->first();
                            $entregada = ($solicitud->entregada_at ?? $solicitud->resuelta_at ?? $solicitud->updated_at)->timezone('America/La_Paz');
                            [$tonoSeg, $textoSeg] = $seguimientoChip[$solicitud->seguimiento?->estado] ?? [null, null];
                            $icono = 'h-5 w-5 shrink-0 text-slate-500';
                        @endphp
                        {{-- Fila horizontal (mismo estilo que Seguimientos): foto, mascota y familia, acta y seguimiento, botón. --}}
                        <li class="flex flex-col gap-4 rounded-2xl border border-s-[6px] border-slate-200 border-s-emerald-500 bg-white p-3 shadow-sm transition hover:border-emerald-300 hover:shadow-md sm:flex-row sm:flex-wrap sm:items-center xl:flex-nowrap">
                            <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="block shrink-0" tabindex="-1" aria-hidden="true">
                                @if ($foto)
                                    <img src="{{ $foto->url_miniatura }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-xl bg-slate-100 object-cover sm:h-24 sm:w-32">
                                @else
                                    <span class="flex aspect-[4/3] w-full items-center justify-center rounded-xl bg-emerald-50 text-4xl sm:h-24 sm:w-32">{{ $solicitud->mascota->especie === 'gato' ? '🐱' : '🐶' }}</span>
                                @endif
                            </a>

                            {{-- Mascota y familia --}}
                            <div class="min-w-[13rem] flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="text-lg font-bold text-blue-950 hover:underline">{{ $solicitud->mascota->nombre }}</a>
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-[13px] font-semibold text-emerald-800">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                        Adopción concretada
                                    </span>
                                </div>
                                <p class="mt-1 flex items-center gap-2 text-sm text-slate-700">
                                    <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" /></svg>
                                    <span class="min-w-0">Adoptada por: <span class="font-semibold text-slate-900">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</span></span>
                                </p>
                                <p class="mt-0.5 flex items-center gap-2 text-sm text-slate-700">
                                    <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></svg>
                                    Entrega: <time datetime="{{ $entregada->toIso8601String() }}">{{ $entregada->format('d/m/Y') }}</time>
                                </p>
                            </div>

                            {{-- Acta y seguimiento --}}
                            <div class="min-w-[15rem] flex-1 space-y-1 border-slate-200 text-sm text-slate-700 sm:border-s sm:ps-5">
                                @if ($solicitud->numero_acta)
                                    <p class="flex items-center gap-2">
                                        <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /></svg>
                                        Acta: <span class="font-mono font-medium text-slate-800">{{ $solicitud->numero_acta }}</span>
                                    </p>
                                @endif
                                @if ($textoSeg)
                                    <p class="flex items-center gap-2">
                                        <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Z" /></svg>
                                        <span class="{{ $chip }} {{ $tonoSeg }}">{{ $textoSeg }}</span>
                                    </p>
                                @endif
                            </div>

                            <div class="shrink-0 sm:w-full sm:text-end xl:w-52">
                                <x-admin.boton variante="secundario" class="w-full sm:w-auto" :href="route('admin.solicitudes-adopcion.show', $solicitud)">Ver adopción<span class="sr-only"> de {{ $solicitud->mascota->nombre }}</span></x-admin.boton>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Cerradas sin adopción: una línea gris, más baja, para que no compitan con las que necesitan atención. --}}
        @if ($cerradas->isNotEmpty())
            <section aria-labelledby="titulo-finalizadas" class="space-y-2">
                <h2 id="titulo-finalizadas" class="text-base font-semibold text-slate-600">Cerradas sin adopción</h2>
                <ul class="space-y-1.5">
                    @foreach ($cerradas as $solicitud)
                        <li class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-100/70 px-4 py-2 sm:flex-row sm:items-center sm:px-5">
                            <p class="min-w-0 flex-1 text-sm text-slate-600">
                                <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="font-semibold text-slate-800 hover:underline">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</a>
                                <span aria-hidden="true">·</span> {{ $solicitud->mascota->nombre }}
                                <span aria-hidden="true">·</span> <span class="font-semibold {{ $coloresFinal[$solicitud->estado] ?? 'text-slate-700' }}">{{ $solicitud->estadoLegible() }}</span>
                                <span aria-hidden="true">·</span> {{ ($solicitud->resuelta_at ?? $solicitud->updated_at ?? $solicitud->created_at)->timezone('America/La_Paz')->format('d/m/Y') }}
                            </p>
                            <x-admin.boton variante="secundario" tamano="chico" class="self-start sm:self-center" :href="route('admin.solicitudes-adopcion.show', $solicitud)">Ver detalle</x-admin.boton>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($solicitudes->hasPages())<div>{{ $solicitudes->links() }}</div>@endif
    @endif
</x-admin.pagina>
