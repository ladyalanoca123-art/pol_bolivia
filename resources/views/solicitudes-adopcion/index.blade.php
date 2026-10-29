@php
    $huella = '<svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg>';
    $esCiudadano = auth()->user()->hasRole('ciudadano');
@endphp
<x-admin.pagina titulo="Mis solicitudes de adopción" seccion="Mi espacio" descripcion="Aquí ves en qué etapa está cada solicitud: Recibida, En evaluación, Reservada y Adopción concretada.">
    @if ($esCiudadano)
        <x-slot name="acciones">
            <x-admin.boton variante="destacado" :href="route('mascotas.catalogo')">Ver mascotas disponibles</x-admin.boton>
        </x-slot>
    @endif

    @if ($errors->has('mascota'))
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="alert">{{ $errors->first('mascota') }}</div>
    @endif

    @if ($solicitudes->total() === 0)
        <x-admin.tarjeta>
            <div class="px-2 py-8 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-300">{!! $huella !!}</div>
                @if ($esCiudadano)
                    <h2 class="mt-4 text-lg font-bold text-blue-950">Todavía no tienes solicitudes de adopción</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">Cuando envíes una solicitud para adoptar una mascota, aparecerá aquí junto con su estado.</p>
                    <x-admin.boton variante="destacado" class="mt-6" :href="route('mascotas.catalogo')">Ver mascotas disponibles</x-admin.boton>
                @else
                    {{-- Voluntario: solo consulta su historial, no puede enviar solicitudes nuevas --}}
                    <h2 class="mt-4 text-lg font-bold text-blue-950">No tienes solicitudes de adopción registradas</h2>
                @endif
            </div>
        </x-admin.tarjeta>
    @else
        <ul class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($solicitudes as $solicitud)
                @php($etapa = $solicitud->etapaAlcanzada())
                <li>
                    <a href="{{ route('solicitudes-adopcion.show', $solicitud) }}" class="flex h-full gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 sm:p-5">
                        <x-foto-mascota :mascota="$solicitud->mascota" class="h-20 w-20 rounded-xl" />

                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h2 class="truncate text-base font-semibold text-blue-950">{{ $solicitud->mascota->nombre }}</h2>
                                <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $solicitud->queSigue() }}</p>
                            <p class="mt-1 text-xs text-slate-400">Enviada el {{ $solicitud->created_at->format('d/m/Y') }}</p>
                            <div class="mt-auto flex items-center gap-1.5 pt-3" aria-label="Etapa {{ $etapa }} de 4">
                                @for ($i = 1; $i <= 4; $i++)
                                    <span class="h-1.5 flex-1 rounded-full {{ $i <= $etapa ? ($solicitud->terminoSinAdopcion() && $i === $etapa ? 'bg-slate-400' : 'bg-blue-950') : 'bg-slate-200' }}"></span>
                                @endfor
                                <span class="ms-2 whitespace-nowrap text-sm font-semibold text-blue-800">Ver detalle <span aria-hidden="true">›</span></span>
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($solicitudes->hasPages())<div>{{ $solicitudes->links() }}</div>@endif
    @endif
</x-admin.pagina>
