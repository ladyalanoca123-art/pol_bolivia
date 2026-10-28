<x-admin.pagina titulo="Editar mascota" :seccion="$mascota->nombre" descripcion="Las fotos se cambian desde el detalle de la mascota." ancho="max-w-4xl" :volver="route('admin.mascotas.show', $mascota)" volver-texto="Volver al detalle">
    <form method="POST" action="{{ route('admin.mascotas.update', $mascota) }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <x-admin.tarjeta titulo="Datos de la mascota">
            @include('admin.mascotas.partials.campos', ['mascota' => $mascota])
        </x-admin.tarjeta>

        <div class="flex flex-wrap items-center gap-3">
            <x-admin.boton>Guardar cambios</x-admin.boton>
            <x-admin.boton variante="secundario" :href="route('admin.mascotas.show', $mascota)">Cancelar</x-admin.boton>
        </div>
    </form>
</x-admin.pagina>
