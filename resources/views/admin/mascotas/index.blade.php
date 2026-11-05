@php
    $tamanos = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'];
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp
<x-admin.pagina titulo="Gestión de mascotas" seccion="Mascotas" descripcion="Registra mascotas, actualiza sus datos y fotos, y revisa cuáles están disponibles, reservadas o adoptadas.">
    <x-slot name="acciones">
        <x-admin.boton variante="destacado" :href="route('admin.mascotas.create')"><span aria-hidden="true">+</span> Registrar mascota</x-admin.boton>
    </x-slot>

    <x-admin.tarjeta>
        <form method="GET" action="{{ route('admin.mascotas.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="sm:col-span-2">
                <label for="buscar" class="admin-etiqueta">Buscar</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre de la mascota">
            </div>
            <div>
                <label for="especie" class="admin-etiqueta">Especie</label>
                <select id="especie" name="especie" class="admin-campo">
                    <option value="">Todas</option>
                    @foreach (['perro' => 'Perro', 'gato' => 'Gato'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filters['especie'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sexo" class="admin-etiqueta">Sexo</label>
                <select id="sexo" name="sexo" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (['macho' => 'Macho', 'hembra' => 'Hembra'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filters['sexo'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tamano" class="admin-etiqueta">Tamaño</label>
                <select id="tamano" name="tamano" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach ($tamanos as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filters['tamano'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="estado" class="admin-etiqueta">Estado</label>
                <select id="estado" name="estado" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Mascota::ESTADOS_LEGIBLES as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filters['estado'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-6">
                <x-admin.boton>Buscar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.mascotas.index') }}" class="admin-boton-borde">Limpiar</a>@endif
                <p class="text-sm text-slate-500 sm:ms-auto">{{ $mascotas->total() }} {{ $mascotas->total() === 1 ? 'mascota' : 'mascotas' }}</p>
            </div>
        </form>
    </x-admin.tarjeta>

    <x-admin.tarjeta :relleno="false">
        @if ($mascotas->isEmpty())
            <x-admin.vacio titulo="No se encontraron mascotas." :texto="$hayFiltros ? 'Prueba con otros filtros.' : 'Registra la primera mascota para que aparezca en el catálogo.'" />
        @else
            {{-- Tarjetas con la foto grande (miniatura de 400 px) y los datos esenciales. --}}
            <ul class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($mascotas as $mascota)
                    @php($fotoPrincipal = $mascota->fotos->first())
                    <li class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-blue-200 hover:shadow-md">
                        <a href="{{ route('admin.mascotas.show', $mascota) }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400" tabindex="-1" aria-hidden="true">
                            @if ($fotoPrincipal)
                                <img src="{{ $fotoPrincipal->url_miniatura }}" alt="" class="aspect-[4/3] w-full bg-slate-100 object-cover">
                            @else
                                <span class="flex aspect-[4/3] w-full items-center justify-center bg-slate-100 text-sm font-medium text-slate-500">Sin foto</span>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a href="{{ route('admin.mascotas.show', $mascota) }}" class="truncate text-lg font-bold text-blue-950 hover:underline">{{ $mascota->nombre }}</a>
                                @if ($mascota->estado === 'reservada')
                                    <x-estado-mascota :mascota="$mascota" />
                                @else
                                    <x-admin.estado de="mascota" :valor="$mascota->estado" />
                                @endif
                            </div>
                            <p class="mt-1.5 text-[15px] text-slate-700">{{ ucfirst($mascota->especie) }} · {{ $mascota->edad_formateada }} · {{ ucfirst($mascota->sexo) }}</p>
                            <p class="text-sm text-slate-600">{{ $mascota->raza ?? 'Raza sin indicar' }} · {{ $tamanos[$mascota->tamano] ?? ucfirst($mascota->tamano) }}</p>
                            <p class="mt-1 text-sm text-slate-500">Registrada el {{ $mascota->created_at->format('d/m/Y H:i') }}</p>
                            <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.mascotas.show', $mascota)" class="mt-4 w-full">Ver detalle<span class="sr-only"> de {{ $mascota->nombre }}</span></x-admin.boton>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($mascotas->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $mascotas->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
