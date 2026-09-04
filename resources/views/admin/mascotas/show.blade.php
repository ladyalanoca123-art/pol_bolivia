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
                        <div><dt class="font-medium text-gray-500">Edad</dt><dd class="mt-1 text-gray-900">{{ $mascota->edad }} años</dd></div>
                        <div><dt class="font-medium text-gray-500">Sexo</dt><dd class="mt-1 text-gray-900">{{ ucfirst($mascota->sexo) }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Tamaño</dt><dd class="mt-1 text-gray-900">{{ ucfirst($mascota->tamano) }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Color</dt><dd class="mt-1 text-gray-900">{{ $mascota->color ?? '—' }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Estado</dt><dd class="mt-1 text-gray-900">{{ ucfirst($mascota->estado) }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Registrada por</dt><dd class="mt-1 text-gray-900">{{ $mascota->registradoPor->nombres }} {{ $mascota->registradoPor->apellidos }}</dd></div>
                        <div><dt class="font-medium text-gray-500">Fecha de registro</dt><dd class="mt-1 text-gray-900">{{ $mascota->created_at->format('d/m/Y H:i') }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-500">Descripción</dt><dd class="mt-1 whitespace-pre-line text-gray-900">{{ $mascota->descripcion ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Agregar foto</h3>

                <form method="POST" action="{{ route('admin.mascotas.fotos.store', $mascota) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="foto" :value="__('Imagen')" />
                        <input id="foto" name="foto" type="file" accept="image/*" required class="mt-1 block w-full text-sm text-gray-700">
                        <x-input-error :messages="$errors->get('foto')" class="mt-2" />
                    </div>
                    <x-primary-button>Subir foto</x-primary-button>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Fotos</h3>

                @forelse ($mascota->fotos as $foto)
                    <div class="mt-4 flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-center">
                        <img src="{{ Storage::disk('public')->url($foto->url) }}" alt="Foto de {{ $mascota->nombre }}" class="h-24 w-24 rounded-md object-cover">
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
                            <form method="POST" action="{{ route('admin.mascotas.fotos.destroy', [$mascota, $foto]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:text-red-900">Eliminar</button>
                            </form>
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
