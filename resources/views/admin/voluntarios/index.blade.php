<x-admin.pagina titulo="Voluntarios" :contador="$voluntarios->total().($voluntarios->total() === 1 ? ' voluntario' : ' voluntarios')" seccion="Voluntariado" descripcion="Personas aprobadas como voluntarias. Se asignan a los seguimientos desde el detalle de cada seguimiento.">
    @if ($voluntarios->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio titulo="No hay voluntarios." texto="Aparecen aquí cuando se aprueba una postulación de voluntariado.">
                <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.postulaciones-voluntariado.index')">Ver postulaciones</x-admin.boton>
            </x-admin.vacio>
        </x-admin.tarjeta>
    @else
        {{-- Tarjetas del equipo: zona, horario y cuántos seguimientos activos tiene cada uno (para saber quién está libre). --}}
        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($voluntarios as $voluntario)
                @php
                    $nombre = trim($voluntario->usuario->nombres.' '.$voluntario->usuario->apellidos);
                    $activo = $voluntario->estado === 'activo';
                    $carga = (int) ($seguimientosActivos[$voluntario->usuario_id] ?? 0);
                @endphp
                <li class="flex flex-col items-center rounded-2xl border p-5 text-center shadow-sm transition {{ $activo ? 'border-slate-200 bg-white hover:border-blue-200 hover:shadow-md' : 'border-slate-200 bg-slate-100/70' }}">
                    <span aria-hidden="true" class="flex h-14 w-14 items-center justify-center rounded-full text-xl font-bold {{ $activo ? 'bg-blue-950 text-amber-300' : 'bg-slate-300 text-slate-600' }}">{{ mb_strtoupper(mb_substr($voluntario->usuario->nombres, 0, 1)) }}</span>
                    <a href="{{ route('admin.voluntarios.show', $voluntario) }}" class="mt-3 text-base font-semibold hover:underline {{ $activo ? 'text-blue-950' : 'text-slate-600' }}">{{ $nombre }}</a>
                    @unless ($activo)<x-admin.estado de="voluntario" :valor="$voluntario->estado" class="mt-1" />@endunless

                    <dl class="mt-2 space-y-0.5 text-sm text-slate-600">
                        <div class="flex items-center justify-center gap-1.5">
                            <dt><svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /></svg><span class="sr-only">Zona</span></dt>
                            <dd>{{ $voluntario->zona_preferencia ?: 'Zona sin indicar' }}</dd>
                        </div>
                        <div class="flex items-center justify-center gap-1.5">
                            <dt><svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v4l3 2" /></svg><span class="sr-only">Disponibilidad</span></dt>
                            <dd class="line-clamp-2">{{ $voluntario->disponibilidad ?: 'Disponibilidad sin indicar' }}</dd>
                        </div>
                    </dl>

                    <p class="mt-3">
                        @if (! $activo)
                            <span class="whitespace-nowrap rounded-full bg-slate-200 px-3 py-1 text-[13px] font-semibold text-slate-600">No recibe seguimientos</span>
                        @elseif ($carga > 0)
                            <span class="whitespace-nowrap rounded-full bg-blue-100 px-3 py-1 text-[13px] font-semibold text-blue-950">{{ $carga }} {{ $carga === 1 ? 'seguimiento activo' : 'seguimientos activos' }}</span>
                        @else
                            <span class="whitespace-nowrap rounded-full bg-emerald-100 px-3 py-1 text-[13px] font-semibold text-emerald-800">Libre · sin seguimientos</span>
                        @endif
                    </p>

                    <div class="mt-auto w-full pt-4">
                        <x-admin.boton variante="secundario" tamano="chico" class="w-full" :href="route('admin.voluntarios.show', $voluntario)">Ver detalle<span class="sr-only"> de {{ $nombre }}</span></x-admin.boton>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($voluntarios->hasPages())<div>{{ $voluntarios->links() }}</div>@endif
    @endif
</x-admin.pagina>
