{{-- Lista del voluntario: una fila por seguimiento, como la del administrador (foto, familia, visitas e incidencias). --}}
@php
    $visitasTotales = count(config('pol.seguimiento.visitas', [])) ?: 2;
    $bordes = ['activo' => 'border-s-blue-500', 'cerrado' => 'border-s-slate-300', 'sin_asignar' => 'border-s-amber-400'];
@endphp
<x-admin.pagina titulo="Mis seguimientos" seccion="Mi espacio" descripcion="Las adopciones que acompañas: programa las 2 visitas, registra lo que observas y reporta cualquier problema."
    :contador="$seguimientos->total().($seguimientos->total() === 1 ? ' seguimiento' : ' seguimientos')">
    @if ($seguimientos->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio titulo="No tienes seguimientos asignados." texto="Cuando la fundación te asigne una adopción, aparecerá aquí." />
        </x-admin.tarjeta>
    @else
        <ul class="space-y-3">
            @foreach ($seguimientos as $seguimiento)
                @php
                    $mascota = $seguimiento->solicitud->mascota;
                    $foto = $mascota->fotos->firstWhere('es_principal', true) ?? $mascota->fotos->first();
                    $familia = $seguimiento->solicitud->solicitante;
                    $hechas = min($seguimiento->visitas_hechas_count, $visitasTotales);
                    $pendientes = $seguimiento->incidencias_pendientes_count;
                @endphp
                <li class="flex flex-col gap-4 rounded-2xl border border-s-[6px] border-slate-200 {{ $bordes[$seguimiento->estado] ?? 'border-s-slate-300' }} bg-white p-3 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center">
                    <a href="{{ route('seguimientos.show', $seguimiento) }}" class="block shrink-0" tabindex="-1" aria-hidden="true">
                        @if ($foto)
                            <img src="{{ $foto->url_miniatura }}" alt="" class="aspect-[4/3] w-full rounded-xl bg-slate-100 object-cover sm:h-20 sm:w-28">
                        @else
                            <span class="flex aspect-[4/3] w-full items-center justify-center rounded-xl bg-slate-100 text-3xl sm:h-20 sm:w-28">{{ $mascota->especie === 'gato' ? '🐱' : '🐶' }}</span>
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('seguimientos.show', $seguimiento) }}" class="text-lg font-bold text-blue-950 hover:underline">{{ $mascota->nombre }}</a>
                            <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
                        </div>
                        <p class="mt-0.5 text-sm text-slate-700">Familia: {{ $familia ? trim($familia->nombres.' '.$familia->apellidos) : '—' }}</p>
                        <p class="text-[13px] text-slate-500">Desde el {{ ($seguimiento->fecha_inicio ?? $seguimiento->created_at)->format('d/m/Y') }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-semibold {{ $hechas >= $visitasTotales ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-950' }}">{{ $hechas }} de {{ $visitasTotales }} visitas</span>
                            @if ($pendientes > 0)
                                <span class="whitespace-nowrap rounded-full bg-red-100 px-2.5 py-0.5 text-[13px] font-semibold text-red-800">{{ $pendientes }} {{ $pendientes === 1 ? 'incidencia pendiente' : 'incidencias pendientes' }}</span>
                            @endif
                        </div>
                    </div>
                    <x-admin.boton :variante="$seguimiento->estado === 'activo' && $hechas < $visitasTotales ? 'primario' : 'secundario'" class="w-full shrink-0 sm:w-auto" :href="route('seguimientos.show', $seguimiento)">
                        {{ $seguimiento->estado === 'activo' && $hechas < $visitasTotales ? 'Ver y registrar visita' : 'Ver detalle' }}<span class="sr-only"> de {{ $mascota->nombre }}</span>
                    </x-admin.boton>
                </li>
            @endforeach
        </ul>
        @if ($seguimientos->hasPages())<div>{{ $seguimientos->links() }}</div>@endif
    @endif
</x-admin.pagina>
