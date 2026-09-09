<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar mascota</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.mascotas.update', $mascota) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <x-input-label for="nombre" :value="__('Nombre')" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $mascota->nombre)" required autofocus />
                            <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="especie" :value="__('Especie')" />
                            <select id="especie" name="especie" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (['perro' => 'Perro', 'gato' => 'Gato'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('especie', $mascota->especie) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('especie')" class="mt-2" />
                        </div>
                        <div><x-input-label for="raza" :value="__('Raza')" /><x-text-input id="raza" name="raza" type="text" class="mt-1 block w-full" :value="old('raza', $mascota->raza)" /><x-input-error :messages="$errors->get('raza')" class="mt-2" /></div>
                        <div><x-input-label for="edad" :value="__('Edad')" /><x-text-input id="edad" name="edad" type="number" min="0" class="mt-1 block w-full" :value="old('edad', $mascota->edad)" required /><x-input-error :messages="$errors->get('edad')" class="mt-2" /></div>
                        <div>
                            <x-input-label for="sexo" :value="__('Sexo')" />
                            <select id="sexo" name="sexo" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (['macho' => 'Macho', 'hembra' => 'Hembra'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('sexo', $mascota->sexo) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sexo')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tamano" :value="__('Tamaño')" />
                            <select id="tamano" name="tamano" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('tamano', $mascota->tamano) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('tamano')" class="mt-2" />
                        </div>
                        <div><x-input-label for="color" :value="__('Color')" /><x-text-input id="color" name="color" type="text" class="mt-1 block w-full" :value="old('color', $mascota->color)" /><x-input-error :messages="$errors->get('color')" class="mt-2" /></div>
                    </div>

                    <div><x-input-label for="descripcion" :value="__('Descripción')" /><textarea id="descripcion" name="descripcion" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('descripcion', $mascota->descripcion) }}</textarea><x-input-error :messages="$errors->get('descripcion')" class="mt-2" /></div>

                    <div class="flex items-center gap-4"><x-primary-button>Guardar cambios</x-primary-button><a href="{{ route('admin.mascotas.show', $mascota) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a></div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
