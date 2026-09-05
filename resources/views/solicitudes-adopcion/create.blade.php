<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Solicitar adopción</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Solicitud para {{ $mascota->nombre }}</h3>
                <p class="mt-1 text-sm text-gray-600">Completa la información para registrar tu solicitud de adopción.</p>

                @if ($errors->has('mascota'))
                    <p class="mt-4 text-sm text-red-600">{{ $errors->first('mascota') }}</p>
                @endif

                <form method="POST" action="{{ route('solicitudes-adopcion.store', $mascota) }}" class="mt-6 space-y-6">
                    @csrf

                    @foreach ([
                        'tiene_otras_mascotas' => '¿Tienes otras mascotas?',
                        'viven_otras_personas' => '¿Viven otras personas contigo?',
                        'tiene_espacio_adecuado' => '¿Cuentas con espacio adecuado para la mascota?',
                    ] as $field => $label)
                        <fieldset>
                            <legend class="text-sm font-medium text-gray-700">{{ $label }}</legend>
                            <div class="mt-2 flex gap-4 text-sm text-gray-700">
                                <label><input type="radio" name="{{ $field }}" value="1" @checked(old($field) === '1') required> Sí</label>
                                <label><input type="radio" name="{{ $field }}" value="0" @checked(old($field) === '0') required> No</label>
                            </div>
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </fieldset>
                    @endforeach

                    <div>
                        <x-input-label for="zona_aproximada" value="Zona aproximada" />
                        <x-text-input id="zona_aproximada" name="zona_aproximada" type="text" class="mt-1 block w-full" :value="old('zona_aproximada')" required />
                        <x-input-error :messages="$errors->get('zona_aproximada')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="horario_preferido" value="Horario preferido" />
                        <x-text-input id="horario_preferido" name="horario_preferido" type="text" class="mt-1 block w-full" :value="old('horario_preferido')" required />
                        <x-input-error :messages="$errors->get('horario_preferido')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="comentarios_solicitante" value="Comentarios adicionales (opcional)" />
                        <textarea id="comentarios_solicitante" name="comentarios_solicitante" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comentarios_solicitante') }}</textarea>
                        <x-input-error :messages="$errors->get('comentarios_solicitante')" class="mt-2" />
                    </div>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="declara_informacion_veraz" value="1" @checked(old('declara_informacion_veraz')) required>
                        <span>Declaro que la información proporcionada es veraz.</span>
                    </label>
                    <x-input-error :messages="$errors->get('declara_informacion_veraz')" class="mt-2" />

                    <div class="flex items-center gap-4">
                        <x-primary-button>Enviar solicitud</x-primary-button>
                        <a href="{{ route('solicitudes-adopcion.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
