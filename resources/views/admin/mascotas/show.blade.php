@php
    $tamanos = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'];
    $maximoFotos = \App\Models\FotoMascota::MAXIMO_POR_MASCOTA;
    $cantidadFotos = $mascota->fotos->count();
    $espaciosLibres = max(0, $maximoFotos - $cantidadFotos);
    $unicaFotoProtegida = $cantidadFotos === 1 && in_array($mascota->estado, ['disponible', 'reservada'], true);
    // Errores de la subida ("fotos", "fotos.N" o el campo antiguo "foto") y de la eliminación ("fotos").
    $erroresFotos = [...$errors->get('foto'), ...$errors->get('fotos'), ...\Illuminate\Support\Arr::flatten($errors->get('fotos.*'))];
@endphp
<x-admin.pagina :titulo="$mascota->nombre" seccion="Detalle de mascota" :volver="route('admin.mascotas.index')" volver-texto="Volver a mascotas">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($mascota->estado === 'reservada')<x-estado-mascota :mascota="$mascota" />@else<x-admin.estado de="mascota" :valor="$mascota->estado" />@endif
            <span class="text-sm text-slate-500">Registrada el {{ $mascota->created_at->format('d/m/Y H:i') }} por {{ $mascota->registradoPor->nombres }} {{ $mascota->registradoPor->apellidos }}</span>
        </div>
    </x-slot>
    <x-slot name="acciones">
        <x-admin.boton variante="secundario" :href="route('admin.mascotas.edit', $mascota)">Editar información</x-admin.boton>
    </x-slot>

    <x-admin.tarjeta>
        <div class="flex flex-col gap-6 md:flex-row">
            <div class="shrink-0">
                @if ($fotoPrincipal)
                    <img src="{{ Storage::disk('public')->url($fotoPrincipal->url) }}" alt="{{ $mascota->nombre }}" class="h-56 w-full rounded-xl object-cover md:w-56">
                @else
                    <div class="flex h-56 w-full items-center justify-center rounded-xl bg-slate-100 text-sm text-slate-500 md:w-56">Sin foto principal</div>
                @endif
            </div>
            <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                <x-admin.dato etiqueta="Especie">{{ ucfirst($mascota->especie) }}</x-admin.dato>
                <x-admin.dato etiqueta="Raza">{{ $mascota->raza ?? '—' }}</x-admin.dato>
                <x-admin.dato etiqueta="Edad">{{ $mascota->edad_formateada }}</x-admin.dato>
                <x-admin.dato etiqueta="Sexo">{{ ucfirst($mascota->sexo) }}</x-admin.dato>
                <x-admin.dato etiqueta="Tamaño">{{ $tamanos[$mascota->tamano] ?? ucfirst($mascota->tamano) }}</x-admin.dato>
                <x-admin.dato etiqueta="Color">{{ $mascota->color ?? '—' }}</x-admin.dato>
                <x-admin.dato etiqueta="Descripción" class="col-span-2 sm:col-span-3"><span class="whitespace-pre-line">{{ $mascota->descripcion ?? '—' }}</span></x-admin.dato>
            </dl>
        </div>
    </x-admin.tarjeta>

    <x-admin.tarjeta titulo="Fotos" :descripcion="'Fotos: '.$cantidadFotos.' de '.$maximoFotos.($espaciosLibres > 0 ? ' · Puedes agregar '.$espaciosLibres.' más' : '')">
        @if ($espaciosLibres > 0)
            <form method="POST" action="{{ route('admin.mascotas.fotos.store', $mascota) }}" enctype="multipart/form-data" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4"
                  x-data="optimizadorFotos({ maximoBytes: {{ config('pol.mascotas.foto_max_kb') * 1024 }} })" x-on:submit="enviar($event)" x-on:pageshow.window="enviando = false">
                @csrf
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="fotos" class="admin-etiqueta">Agregar fotos</label>
                        <input id="fotos" name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-maximo="{{ $espaciosLibres }}" data-aviso="{{ \App\Models\FotoMascota::mensajeEspaciosLibres($espaciosLibres) }}"
                               x-on:change="optimizar($event.target)" class="admin-archivo">
                    </div>
                    <x-admin.boton x-bind:disabled="bloqueado || enviando">
                        <span x-text="enviando ? textoEnviando : (procesando ? 'Optimizando fotos…' : 'Subir fotos')">Subir fotos</span>
                    </x-admin.boton>
                </div>
                @include('admin.mascotas.partials.ayuda-fotos', ['campo' => 'fotos[]'])
                <x-input-error :messages="$erroresFotos" class="mt-2" />
            </form>
        @else
            <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Esta mascota ya tiene el máximo de {{ $maximoFotos }} fotos. Elimina una para subir otra.</p>
            <x-input-error :messages="$erroresFotos" class="mt-2" />
        @endif

        @if ($mascota->fotos->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Esta mascota todavía no tiene fotos.</p>
        @else
            <ul class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($mascota->fotos as $foto)
                    <li class="overflow-hidden rounded-xl border {{ $foto->es_principal ? 'border-emerald-300 ring-1 ring-emerald-300' : 'border-slate-200' }}">
                        <img src="{{ $foto->url_miniatura }}" alt="Foto de {{ $mascota->nombre }}" class="h-40 w-full object-cover">
                        <div class="space-y-2 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-600">Orden: {{ $foto->orden }}</span>
                                @if ($foto->es_principal)<x-admin.insignia tono="verde">Foto principal</x-admin.insignia>@endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if (! $foto->es_principal)
                                    <form method="POST" action="{{ route('admin.mascotas.fotos.principal', [$mascota, $foto]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-admin.boton variante="secundario" tamano="chico">Establecer principal</x-admin.boton>
                                    </form>
                                @endif
                                @if ($unicaFotoProtegida)
                                    <span class="text-xs text-slate-500">Es la única foto: agrega otra antes de eliminarla.</span>
                                @else
                                    <form method="POST" action="{{ route('admin.mascotas.fotos.destroy', [$mascota, $foto]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-admin.boton variante="peligro-suave" tamano="chico">Eliminar</x-admin.boton>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
