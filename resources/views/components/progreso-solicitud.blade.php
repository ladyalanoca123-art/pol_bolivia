@props(['solicitud'])
{{--
    Línea de progreso de una solicitud: Recibida → En evaluación → Reservada → Adopción concretada.
    Las etapas completadas en verde, la actual resaltada y, si terminó sin adopción, la etapa donde se detuvo en rojo.
--}}
@php
    $etiquetas = [1 => 'Recibida', 2 => 'En evaluación', 3 => 'Reservada', 4 => 'Adopción concretada'];
    $alcanzada = $solicitud->etapaAlcanzada();
    $detenida = $solicitud->terminoSinAdopcion();
@endphp
<ol class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Etapas de la solicitud">
    @foreach ($etiquetas as $numero => $etiqueta)
        @php
            [$clase, $estado] = match (true) {
                $numero < $alcanzada || ($numero === 4 && $alcanzada === 4) => ['border-green-500 bg-green-50 text-green-900', 'completada'],
                $numero === $alcanzada && $detenida => ['border-red-400 bg-red-50 text-red-900', 'detenida'],
                $numero === $alcanzada => ['border-indigo-600 bg-indigo-50 text-indigo-900 ring-2 ring-indigo-600', 'actual'],
                default => ['border-gray-200 bg-white text-gray-500', 'pendiente'],
            };
        @endphp
        <li class="rounded-lg border-2 px-3 py-2 text-xs {{ $clase }}" @if ($estado === 'actual' || $estado === 'detenida') aria-current="step" @endif>
            <span class="block font-bold">{{ $numero }} · {{ $etiqueta }}</span>
            <span>{{ ['completada' => 'Completada', 'detenida' => $solicitud->estadoLegible(), 'actual' => 'Etapa actual', 'pendiente' => 'Pendiente'][$estado] }}</span>
        </li>
    @endforeach
</ol>
