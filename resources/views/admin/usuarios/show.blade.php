<x-admin.pagina :titulo="$user->nombres.' '.$user->apellidos" seccion="Detalle de usuario" ancho="max-w-4xl" :volver="route('admin.usuarios.index')" volver-texto="Volver al listado">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-admin.insignia :tono="['administrador' => 'indigo', 'voluntario' => 'ambar'][$user->rol] ?? 'gris'" :punto="false">{{ ucfirst($user->rol) }}</x-admin.insignia>
            @if ($user->es_principal)<x-admin.insignia tono="ambar" :punto="false">Administrador principal</x-admin.insignia>@endif
            <x-admin.estado de="usuario" :valor="(bool) $user->estado" />
        </div>
    </x-slot>
    @can('update', $user)
        <x-slot name="acciones">
            <x-admin.boton variante="secundario" :href="route('admin.usuarios.edit', $user)">Editar usuario</x-admin.boton>
        </x-slot>
    @endcan

    <x-admin.tarjeta titulo="Datos de la cuenta">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-admin.dato etiqueta="Nombres">{{ $user->nombres }}</x-admin.dato>
            <x-admin.dato etiqueta="Apellidos">{{ $user->apellidos }}</x-admin.dato>
            <x-admin.dato etiqueta="Correo">{{ $user->email }}</x-admin.dato>
            <x-admin.dato etiqueta="Teléfono">{{ $user->telefono ?? 'No registrado' }}</x-admin.dato>
            <x-admin.dato etiqueta="Rol">{{ ucfirst($user->rol) }}</x-admin.dato>
            <x-admin.dato etiqueta="Estado">{{ $user->estado ? 'Activo' : 'Inactivo' }}</x-admin.dato>
            <x-admin.dato etiqueta="Fecha de registro">{{ $user->created_at->format('d/m/Y H:i') }}</x-admin.dato>
        </dl>
        @cannot('update', $user)
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Al administrador principal solo lo edita él mismo.</p>
        @endcannot
    </x-admin.tarjeta>
</x-admin.pagina>
