@php
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
    $estadoActual = $filters['estado'] ?? null;
    // Accesos rápidos a las etapas en trámite.
    $rapidos = ['' => 'Todas', 'pendiente' => 'Recibidas', 'en_espera' => 'En evaluación', 'aprobada' => 'Reservadas', 'aceptada' => 'Concretadas'];
@endphp
<x-admin.pagina titulo="Solicitudes de adopción" :contador="$solicitudes->total().($solicitudes->total() === 1 ? ' solicitud' : ' solicitudes')" seccion="Adopciones" descripcion="Primero se muestran las solicitudes en trámite (recibidas, en evaluación y reservadas), ordenadas por nivel de revisión. Las alertas ayudan a priorizar; no aprueban ni rechazan.">

    <nav aria-label="Filtrar por etapa" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($rapidos as $clave => $texto)
            @php $activo = ($estadoActual ?? '') === $clave; @endphp
            <a href="{{ route('admin.solicitudes-adopcion.index', array_filter([...$filters, 'estado' => $clave ?: null])) }}"
               @if ($activo) aria-current="page" @endif
               class="inline-flex min-h-10 items-center whitespace-nowrap rounded-full border px-4 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 {{ $activo ? 'border-blue-950 bg-blue-950 text-white' : 'border-slate-400 bg-white text-blue-950 hover:border-blue-900 hover:bg-blue-50' }}">{{ $texto }}</a>
        @endforeach
    </nav>

    <x-admin.tarjeta>
        <form method="GET" action="{{ route('admin.solicitudes-adopcion.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="buscar" class="admin-etiqueta">Buscar ciudadano o mascota</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre o apellido">
            </div>
            <div>
                <label for="estado" class="admin-etiqueta">Estado</label>
                <select id="estado" name="estado" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES as $estado => $texto)
                        <option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ $texto }}</option>
                    @endforeach
                </select>
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
                <x-admin.boton>Filtrar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.solicitudes-adopcion.index') }}" class="admin-boton-borde">Limpiar</a>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    @if ($solicitudes->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio titulo="No se encontraron solicitudes." :texto="$hayFiltros ? 'Prueba con otro estado o búsqueda.' : 'Cuando un ciudadano envíe una solicitud, aparecerá aquí.'" />
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

        {{-- Finalizadas: una línea gris, más baja, para que no compitan con las que necesitan atención. --}}
        @if ($grupos->has('finalizadas'))
            <section aria-labelledby="titulo-finalizadas" class="space-y-2">
                <h2 id="titulo-finalizadas" class="text-base font-semibold text-slate-600">Finalizadas</h2>
                <ul class="space-y-1.5">
                    @foreach ($grupos['finalizadas'] as $solicitud)
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
