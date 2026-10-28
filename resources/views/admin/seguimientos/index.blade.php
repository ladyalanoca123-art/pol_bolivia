<x-admin.pagina titulo="Seguimientos de adopción" seccion="Seguimientos" descripcion="Cada adopción concretada tiene su seguimiento: asigna un voluntario, revisa sus visitas e incidencias y ciérralo al terminar.">
    <x-admin.tarjeta :relleno="false">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-blue-950">Seguimientos</h2>
            <span class="text-sm text-slate-500">{{ $seguimientos->total() }} {{ $seguimientos->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>
        @if ($seguimientos->isEmpty())
            <x-admin.vacio titulo="No hay seguimientos." texto="Se crean automáticamente al registrar la entrega de una adopción." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($seguimientos as $seguimiento)
                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.seguimientos.show', $seguimiento) }}" class="font-semibold text-blue-950 hover:underline">{{ $seguimiento->solicitud->mascota->nombre }}</a>
                                <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                Familia: {{ $seguimiento->solicitud->solicitante->nombres }} {{ $seguimiento->solicitud->solicitante->apellidos }}
                                <span class="text-slate-400" aria-hidden="true">·</span>
                                Voluntario:
                                @if ($seguimiento->voluntario)
                                    {{ $seguimiento->voluntario->nombres }} {{ $seguimiento->voluntario->apellidos }}
                                @else
                                    <span class="font-semibold text-amber-700">Sin asignar</span>
                                @endif
                            </p>
                        </div>
                        <x-admin.boton :variante="$seguimiento->estado === 'sin_asignar' ? 'primario' : 'secundario'" tamano="chico" :href="route('admin.seguimientos.show', $seguimiento)" class="self-start sm:self-center">
                            {{ $seguimiento->estado === 'sin_asignar' ? 'Asignar voluntario' : 'Ver detalle' }}
                        </x-admin.boton>
                    </li>
                @endforeach
            </ul>
            @if ($seguimientos->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $seguimientos->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
