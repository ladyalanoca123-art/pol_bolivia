<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de mascota</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-6 flex justify-end">
                    <a href="{{ route('admin.mascotas.edit', $mascota) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">Editar información</a>
                </div>
                <div class="flex flex-col gap-6 md:flex-row">
                    <div class="shrink-0">
                        @if ($fotoPrincipal)
                            <img src="{{ Storage::disk('public')->url($fotoPrincipal->url) }}" alt="{{ $mascota->nombre }}" class="h-48 w-48 rounded-lg object-cover">
                        @else
                            <div class="flex h-48 w-48 items-center justify-center rounded-lg bg-gray-100 text-sm text-gray-500">Sin foto principal</div>
                        @endif
                    </div>

                    <dl class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                        <div><dt class="font-medium text-gray-500">Nombre</dt><dd class="mt-1 text-gray-900">{{ $mascota->nombre }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Especie</dt><dd class="mt-1 text-gray-900">{{ ucfirst($mascota->especie) }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Raza</dt><dd class="mt-1 text-gray-900">{{ $mascota->raza ?? '—' }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Edad</dt><dd class="mt-1 text-gray-900">{{ $mascota->edad_formateada }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Sexo</dt><dd class="mt-1 text-gray-900">{{ ucfirst($mascota->sexo) }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Tamaño</dt><dd class="mt-1 text-gray-900">{{ match ($mascota->tamano) { 'pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande', default => ucfirst($mascota->tamano) } }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Color</dt><dd class="mt-1 text-gray-900">{{ $mascota->color ?? '—' }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Estado</dt><dd class="mt-1 text-gray-900">{{ ucfirst($mascota->estado) }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Registrada por</dt><dd class="mt-1 text-gray-900">{{ $mascota->registradoPor->nombres }} {{ $mascota->registradoPor->apellidos }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Fecha de registro</dt><dd class="mt-1 text-gray-900">{{ $mascota->created_at->format('d/m/Y H:i') }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-500">Descripción</dt><dd class="mt-1 whitespace-pre-line text-gray-900">{{ $mascota->descripcion ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>

            @php
                $maximoFotos = \App\Models\FotoMascota::MAXIMO_POR_MASCOTA;
                $cantidadFotos = $mascota->fotos->count();
                $espaciosLibres = max(0, $maximoFotos - $cantidadFotos);
                $unicaFotoProtegida = $cantidadFotos === 1 && $mascota->estado === 'disponible';
                // Errores de la subida ("fotos", "fotos.N" o el campo antiguo "foto") y de la eliminación ("fotos").
                $erroresFotos = [...$errors->get('foto'), ...$errors->get('fotos'), ...\Illuminate\Support\Arr::flatten($errors->get('fotos.*'))];
            @endphp

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Agregar fotos</h3>
                <p class="mt-1 text-sm text-gray-600">Fotos: {{ $cantidadFotos }} de {{ $maximoFotos }}@if ($espaciosLibres > 0) · Puedes agregar {{ $espaciosLibres }} más @endif</p>

                @if ($espaciosLibres > 0)
                    <form method="POST" action="{{ route('admin.mascotas.fotos.store', $mascota) }}" enctype="multipart/form-data" class="mt-4 space-y-3"
                          x-data="optimizadorFotos({ maximoBytes: {{ config('pol.mascotas.foto_max_kb') * 1024 }} })" x-on:submit="enviar($event)" x-on:pageshow.window="enviando = false">
                        @csrf
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <x-input-label for="fotos" :value="__('Imágenes')" />
                                <input id="fotos" name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-maximo="{{ $espaciosLibres }}" data-aviso="{{ \App\Models\FotoMascota::mensajeEspaciosLibres($espaciosLibres) }}"
                                       x-on:change="optimizar($event.target)" class="mt-1 block w-full text-sm text-gray-700">
                            </div>
                            <x-primary-button x-bind:disabled="bloqueado || enviando" class="disabled:cursor-not-allowed disabled:opacity-60">
                                <span x-text="enviando ? textoEnviando : (procesando ? 'Optimizando fotos…' : 'Subir fotos')">Subir fotos</span>
                            </x-primary-button>
                        </div>
                        @include('admin.mascotas.partials.ayuda-fotos', ['campo' => 'fotos[]'])
                        <x-input-error :messages="$erroresFotos" class="mt-2" />
                    </form>
                @else
                    <p class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800">Esta mascota ya tiene el máximo de {{ $maximoFotos }} fotos. Elimina una para subir otra.</p>
                    <x-input-error :messages="$erroresFotos" class="mt-2" />
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Fotos</h3>

                @forelse ($mascota->fotos as $foto)
                    <div class="mt-4 flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-center">
                        <img src="{{ $foto->url_miniatura }}" alt="Foto de {{ $mascota->nombre }}" class="h-24 w-24 rounded-md object-cover">
                        <div class="flex-1 text-sm text-gray-600">
                            <p>Orden: {{ $foto->orden }}</p>
                            @if ($foto->es_principal)
                                <p class="mt-1 font-medium text-green-700">Foto principal</p>
                            @endif
                        </div>
                        <div class="flex gap-4 text-sm">
                            @if (! $foto->es_principal)
                                <form method="POST" action="{{ route('admin.mascotas.fotos.principal', [$mascota, $foto]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="text-indigo-600 hover:text-indigo-900">Establecer principal</button>
                                </form>
                            @endif
                            @if ($unicaFotoProtegida)
                                <span class="text-gray-500">Es la única foto: agrega otra antes de eliminarla.</span>
                            @else
                                <form method="POST" action="{{ route('admin.mascotas.fotos.destroy', [$mascota, $foto]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:text-red-900">Eliminar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-gray-500">Esta mascota todavía no tiene fotos.</p>
                @endforelse
            </div>

            <a href="{{ route('admin.mascotas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Volver al listado</a>
        </div>
    </div>
</x-app-layout>
