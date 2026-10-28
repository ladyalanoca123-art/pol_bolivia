<x-admin.pagina :titulo="$voluntario->usuario->nombres.' '.$voluntario->usuario->apellidos" seccion="Detalle de voluntario" ancho="max-w-4xl" :volver="route('admin.voluntarios.index')" volver-texto="Volver a voluntarios">
    <x-slot name="subtitulo">
        <div class="mt-2"><x-admin.estado de="voluntario" :valor="$voluntario->estado" /></div>
    </x-slot>

    <x-admin.tarjeta titulo="Datos del voluntario">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-admin.dato etiqueta="Nombre">{{ $voluntario->usuario->nombres }} {{ $voluntario->usuario->apellidos }}</x-admin.dato>
            <x-admin.dato etiqueta="Correo">{{ $voluntario->usuario->email }}</x-admin.dato>
            <x-admin.dato etiqueta="Teléfono">{{ $voluntario->usuario->telefono ?: '—' }}</x-admin.dato>
            <x-admin.dato etiqueta="Estado">{{ ucfirst($voluntario->estado) }}</x-admin.dato>
            <x-admin.dato etiqueta="Disponibilidad">{{ $voluntario->disponibilidad ?: '—' }}</x-admin.dato>
            <x-admin.dato etiqueta="Zona">{{ $voluntario->zona_preferencia ?: '—' }}</x-admin.dato>
            @if ($voluntario->habilidades)<x-admin.dato etiqueta="Habilidades" class="sm:col-span-2">{{ $voluntario->habilidades }}</x-admin.dato>@endif
        </dl>
    </x-admin.tarjeta>

    <x-admin.tarjeta titulo="Estado del voluntario">
        @can('deactivate', $voluntario)
            <form method="POST" action="{{ route('admin.voluntarios.deactivate', $voluntario) }}">@csrf @method('PATCH')
                <p class="mb-3 text-sm text-slate-600">Al desactivarlo ya no podrá recibir nuevos seguimientos.</p>
                <x-admin.boton variante="peligro-suave" x-data x-on:click="if (! confirm('¿Desactivar este voluntario?')) $event.preventDefault()">Desactivar voluntario</x-admin.boton>
            </form>
        @elseif ($voluntario->estado === 'activo')
            <p class="text-sm text-slate-500">No puede desactivarse mientras tenga seguimientos activos asignados.</p>
        @else
            <p class="text-sm text-slate-500">La reactivación se realiza mediante una nueva postulación aprobada.</p>
        @endcan
    </x-admin.tarjeta>
</x-admin.pagina>
