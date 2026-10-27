@props(['mascota'])
{{-- Estado de la mascota en la administración. «Reservada» enlaza a la solicitud aprobada que la reserva. --}}
@if ($mascota->estado === 'reservada')
    <span class="inline-flex flex-wrap items-center gap-x-2">
        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Reservada</span>
        @if ($mascota->solicitudAprobada)
            <a href="{{ route('admin.solicitudes-adopcion.show', $mascota->solicitudAprobada) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-900">Ver solicitud que la reserva</a>
        @endif
    </span>
@else
    {{ $mascota->estadoLegible() }}
@endif
