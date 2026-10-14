<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar usuario</h2></x-slot>

    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="p-6 bg-white shadow-sm sm:rounded-lg">
        <form method="POST" action="{{ route('admin.usuarios.update', $user) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div><x-input-label for="nombres" :value="__('Nombres')" /><x-text-input id="nombres" name="nombres" class="block w-full mt-1" :value="old('nombres', $user->nombres)" required /><x-input-error :messages="$errors->get('nombres')" class="mt-2" /></div>
            <div><x-input-label for="apellidos" :value="__('Apellidos')" /><x-text-input id="apellidos" name="apellidos" class="block w-full mt-1" :value="old('apellidos', $user->apellidos)" required /><x-input-error :messages="$errors->get('apellidos')" class="mt-2" /></div>
            <div><x-input-label for="email" :value="__('Correo')" /><x-text-input id="email" name="email" type="email" class="block w-full mt-1" :value="old('email', $user->email)" required /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
            <div><x-input-label for="telefono" :value="__('Teléfono')" /><x-text-input id="telefono" name="telefono" class="block w-full mt-1" :value="old('telefono', $user->telefono)" placeholder="Ej. 71234567" /><x-input-error :messages="$errors->get('telefono')" class="mt-2" /></div>
            <p class="text-sm text-gray-500">El rol y el estado se gestionan mediante sus flujos autorizados.</p>
            <div class="flex items-center gap-4"><x-primary-button>Guardar cambios</x-primary-button><a class="text-gray-600 hover:text-gray-900" href="{{ route('admin.usuarios.show', $user) }}">Cancelar</a></div>
        </form>
    </div></div></div>
</x-app-layout>
