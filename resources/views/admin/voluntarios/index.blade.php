<x-admin.pagina titulo="Voluntarios" seccion="Voluntariado" descripcion="Personas aprobadas como voluntarias. Se asignan a los seguimientos desde el detalle de cada seguimiento.">
    <x-admin.tarjeta :relleno="false">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-blue-950">Voluntarios</h2>
            <span class="text-sm text-slate-500">{{ $voluntarios->total() }} {{ $voluntarios->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>
        @if ($voluntarios->isEmpty())
            <x-admin.vacio titulo="No hay voluntarios." texto="Aparecen aquí cuando se aprueba una postulación de voluntariado.">
                <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.postulaciones-voluntariado.index')">Ver postulaciones</x-admin.boton>
            </x-admin.vacio>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($voluntarios as $voluntario)
                    @php($nombre = trim($voluntario->usuario->nombres.' '.$voluntario->usuario->apellidos))
                    <li class="flex items-center gap-4 px-4 py-4 sm:px-6">
                        <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-amber-300">{{ mb_strtoupper(mb_substr($voluntario->usuario->nombres, 0, 1).mb_substr($voluntario->usuario->apellidos ?? '', 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.voluntarios.show', $voluntario) }}" class="font-semibold text-blue-950 hover:underline">{{ $nombre }}</a>
                                <x-admin.estado de="voluntario" :valor="$voluntario->estado" />
                            </div>
                            <p class="mt-0.5 truncate text-sm text-slate-600">{{ $voluntario->disponibilidad ?: 'Disponibilidad sin indicar' }} · {{ $voluntario->zona_preferencia ?: 'Zona sin indicar' }}</p>
                        </div>
                        <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.voluntarios.show', $voluntario)" class="shrink-0">Ver detalle</x-admin.boton>
                    </li>
                @endforeach
            </ul>
            @if ($voluntarios->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $voluntarios->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
