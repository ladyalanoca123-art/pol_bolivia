@use('App\Support\CuestionarioAdopcion', 'C')
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de solicitud</h2></x-slot>

    @php
        // Mensaje para el ciudadano según la etapa. El ciudadano nunca ve las alertas internas.
        $mensajes = [
            'pendiente' => 'La fundación está revisando tu solicitud.',
            'en_espera' => 'Tu solicitud está en evaluación: la fundación te contactará para la entrevista o la visita.',
            'aceptada' => '¡Felicidades! Tu solicitud fue aceptada. La fundación coordinará la entrega.',
            'rechazada' => 'Tu solicitud no fue aceptada. Puedes comunicarte con la fundación para conocer el motivo.',
            'cancelada' => 'La solicitud fue cancelada.',
            'cerrada' => 'La mascota fue adoptada por otra familia. Gracias por tu interés.',
        ];
    @endphp

    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <div class="rounded-md bg-indigo-50 p-4 text-sm text-indigo-900">
            <p class="font-semibold">Estado: {{ $solicitud->estadoLegible() }}</p>
            <p>{{ $mensajes[$solicitud->estado] ?? '' }}</p>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-medium text-gray-500">Mascota</dt><dd class="mt-1 text-gray-900">{{ $solicitud->mascota->nombre }}</dd></div>
            <div><dt class="font-medium text-gray-500">Fecha de solicitud</dt><dd class="mt-1 text-gray-900">{{ $solicitud->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="font-medium text-gray-500">Zona aproximada</dt><dd class="mt-1 text-gray-900">{{ $solicitud->zona_aproximada }}</dd></div>
            <div><dt class="font-medium text-gray-500">Horario para la visita</dt><dd class="mt-1 text-gray-900">{{ $solicitud->horario_preferido }}</dd></div>
        </dl>

            <h3 class="mt-8 text-base font-semibold text-gray-900">Tus respuestas</h3>
            @foreach (C::SECCIONES as $seccion => $preguntas)
                <h4 class="mt-4 text-xs font-bold uppercase tracking-wider text-gray-500">{{ $seccion }}</h4>
                <dl class="mt-2 divide-y divide-gray-100 border-t border-gray-100 text-sm">
                    @foreach ($preguntas as [$num, $campo, $texto, $catalogo])
                        @php $valor = C::respuestaLegible($solicitud->respuestas, $campo, $catalogo); @endphp
                        @continue($valor === null)
                        <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2">
                            <dt class="text-gray-500">{{ $num }}. {{ $texto }}</dt>
                            <dd class="text-gray-900">{{ $valor }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endforeach

        <a href="{{ route('solicitudes-adopcion.index') }}" class="mt-6 inline-block text-sm text-gray-600 hover:text-gray-900">Volver a mis solicitudes</a>
    </div></div></div>
</x-app-layout>
