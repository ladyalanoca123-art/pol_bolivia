<x-admin.pagina titulo="Mis seguimientos" seccion="Mi espacio" descripcion="Las adopciones que acompañas: programa las 2 visitas, registra lo que observas y reporta cualquier problema.">
    <x-admin.tarjeta :relleno="false">
        @if ($seguimientos->isEmpty())
            <x-admin.vacio titulo="No tienes seguimientos asignados." texto="Cuando la fundación te asigne una adopción, aparecerá aquí." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($seguimientos as $seguimiento)
                    <li>
                        <a href="{{ route('seguimientos.show', $seguimiento) }}" class="flex items-center gap-4 px-4 py-4 hover:bg-slate-50 sm:px-6">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-300" aria-hidden="true">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-blue-950">{{ $seguimiento->solicitud->mascota->nombre }}</span>
                                    <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
                                </div>
                                <p class="mt-0.5 text-sm text-slate-600">Desde el {{ ($seguimiento->fecha_inicio ?? $seguimiento->created_at)->format('d/m/Y') }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-blue-800">Ver detalle <span aria-hidden="true">›</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($seguimientos->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $seguimientos->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
