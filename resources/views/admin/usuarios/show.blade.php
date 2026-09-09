<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de usuario</h2></x-slot>

    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="p-6 space-y-3 bg-white shadow-sm sm:rounded-lg">
        @if (session('status'))<div class="p-4 text-green-700 bg-green-50">{{ session('status') }}</div>@endif
        <p><span class="font-medium">Nombres:</span> {{ $user->nombres }}</p>
        <p><span class="font-medium">Apellidos:</span> {{ $user->apellidos }}</p>
        <p><span class="font-medium">Correo:</span> {{ $user->email }}</p>
        <p><span class="font-medium">Teléfono:</span> {{ $user->telefono ?? 'No registrado' }}</p>
        <p><span class="font-medium">Rol:</span> {{ ucfirst($user->rol) }}</p>
        <p><span class="font-medium">Estado:</span> {{ $user->estado ? 'Activo' : 'Inactivo' }}</p>
        <p><span class="font-medium">Fecha de registro:</span> {{ $user->created_at->format('d/m/Y H:i') }}</p>
        <div class="pt-4 border-t"><a class="text-indigo-600 hover:text-indigo-900" href="{{ route('admin.usuarios.edit', $user) }}">Editar usuario</a><a class="ml-4 text-gray-600 hover:text-gray-900" href="{{ route('admin.usuarios.index') }}">Volver al listado</a></div>
    </div></div></div>
</x-app-layout>
