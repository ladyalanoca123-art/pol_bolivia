@props(['solicitud'])
{{--
    Línea de progreso de una solicitud: Recibida → En evaluación → Reservada → Adopción concretada.
    Etapas completadas en azul, la actual con anillo ámbar y, si terminó sin adopción, la etapa donde se detuvo en gris.
--}}
@php
    $etiquetas = [1 => 'Recibida', 2 => 'En evaluación', 3 => 'Reservada', 4 => 'Adopción concretada'];
    $alcanzada = $solicitud->etapaAlcanzada();
    $detenida = $solicitud->terminoSinAdopcion();
@endphp
<ol class="grid grid-cols-4 gap-2" aria-label="Etapas de la solicitud">
    @foreach ($etiquetas as $numero => $etiqueta)
        @php
            $estado = match (true) {
                $numero < $alcanzada || ($numero === 4 && $alcanzada === 4) => 'completada',
                $numero === $alcanzada && $detenida => 'detenida',
                $numero === $alcanzada => 'actual',
                default => 'pendiente',
            };
            $punto = [
                'completada' => 'bg-blue-950 text-white',
                'detenida' => 'bg-slate-200 text-slate-600',
                'actual' => 'bg-white text-blue-950 ring-2 ring-amber-400',
                'pendiente' => 'bg-white text-slate-400 ring-1 ring-slate-200',
            ][$estado];
        @endphp
        <li class="relative flex flex-col items-center text-center" @if ($estado === 'actual' || $estado === 'detenida') aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute left-1/2 top-4 h-px w-full {{ $numero < $alcanzada ? 'bg-blue-950' : 'bg-slate-200' }}" aria-hidden="true"></span>
            @endunless
            <span class="relative flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold {{ $punto }}">
                @if ($estado === 'completada')
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                @else
                    {{ $numero }}
                @endif
            </span>
            <span class="mt-2 text-xs {{ $estado === 'pendiente' ? 'text-slate-400' : 'font-medium text-blue-950' }}">{{ $etiqueta }}</span>
            <span class="sr-only">: {{ ['completada' => 'Completada', 'detenida' => $solicitud->estadoLegible(), 'actual' => 'Etapa actual', 'pendiente' => 'Pendiente'][$estado] }}</span>
            @if ($estado === 'detenida')<span class="mt-0.5 text-[11px] text-slate-500" aria-hidden="true">{{ $solicitud->estadoLegible() }}</span>@endif
        </li>
    @endforeach
</ol>
