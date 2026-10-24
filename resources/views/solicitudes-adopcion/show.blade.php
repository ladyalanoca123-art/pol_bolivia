@use('App\Support\CuestionarioAdopcion', 'C')
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de solicitud</h2></x-slot>

    @php
        // Los mensajes son informativos; nunca muestran comentarios internos ni resultados privados de evaluación.
        $mensajes = [
            'pendiente' => 'La fundación recibió tu solicitud y está revisando la información.',
            'en_espera' => 'Tu solicitud está en evaluación. La fundación se pondrá en contacto contigo para continuar el proceso.',
            'aprobada' => 'Tu solicitud fue aprobada y la mascota quedó reservada mientras se coordina la entrega.',
            'aceptada' => 'La adopción fue concretada y la entrega quedó registrada. ¡Gracias por darle un hogar!',
            'rechazada' => 'Esta solicitud no fue aprobada. Si necesitas orientación, puedes comunicarte con la fundación.',
            'cancelada' => 'Esta solicitud fue cancelada.',
            'cerrada' => 'La mascota encontró un hogar mediante otra solicitud. Gracias por tu interés.',
            'no_concretada' => 'La entrega no pudo concretarse. La fundación revisará los próximos pasos y se pondrá en contacto contigo.',
        ];
        $coloresEstado = [
            'pendiente' => 'bg-amber-50 text-amber-950',
            'en_espera' => 'bg-blue-50 text-blue-950',
            'aprobada' => 'bg-indigo-50 text-indigo-950',
            'aceptada' => 'bg-green-50 text-green-950',
            'rechazada' => 'bg-red-50 text-red-900',
            'cancelada' => 'bg-gray-100 text-gray-800',
            'cerrada' => 'bg-gray-100 text-gray-800',
            'no_concretada' => 'bg-amber-50 text-amber-950',
        ];
    @endphp

    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        @if (session('status'))
            <div class="mb-5 rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <p class="font-semibold">No se pudo completar la acción.</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="rounded-md p-4 text-sm {{ $coloresEstado[$solicitud->estado] ?? 'bg-gray-100 text-gray-800' }}">
            <p class="font-semibold">Estado: {{ $solicitud->estadoLegible() }}</p>
            <p class="mt-1">{{ $mensajes[$solicitud->estado] ?? 'Consulta aquí el estado actualizado de tu solicitud.' }}</p>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-semibold text-gray-900">Progreso</h3>
            <x-progreso-solicitud :solicitud="$solicitud" />
        </div>

        @if ($solicitud->estado === 'aprobada')
            @if ($solicitud->numero_acta || $solicitud->fecha_entrega_acordada)
                <dl class="mt-5 grid grid-cols-1 gap-4 rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm sm:grid-cols-2">
                    @if ($solicitud->numero_acta)<div><dt class="font-medium text-gray-600">Número de acta</dt><dd class="mt-1 font-semibold text-indigo-950">{{ $solicitud->numero_acta }}</dd></div>@endif
                    @if ($solicitud->fecha_entrega_acordada)<div><dt class="font-medium text-gray-600">Fecha de entrega acordada</dt><dd class="mt-1 text-gray-900">{{ $solicitud->fecha_entrega_acordada->format('d/m/Y') }}</dd></div>@endif
                </dl>
            @endif
        @endif
        @if ($solicitud->estado === 'aceptada' && $solicitud->entregada_at)
            <dl class="mt-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm">
                <div><dt class="font-medium text-green-900">Fecha de entrega registrada</dt><dd class="mt-1 font-semibold text-green-950">{{ $solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y') }}</dd></div>
            </dl>
        @endif

        <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-medium text-gray-500">Mascota</dt><dd class="mt-1 text-gray-900">{{ $solicitud->mascota->nombre }}</dd></div>
            <div><dt class="font-medium text-gray-500">Fecha de solicitud</dt><dd class="mt-1 text-gray-900">{{ $solicitud->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="font-medium text-gray-500">Zona aproximada</dt><dd class="mt-1 text-gray-900">{{ $solicitud->zona_aproximada }}</dd></div>
            <div><dt class="font-medium text-gray-500">Horario para la visita</dt><dd class="mt-1 text-gray-900">{{ $solicitud->horario_preferido }}</dd></div>
        </dl>

        @can('cancel', $solicitud)
            @if ($solicitud->solicitante_id === auth()->id())
                <form method="POST" action="{{ route('solicitudes-adopcion.cancel', $solicitud) }}" class="mt-6 rounded-lg border border-gray-200 p-4"
                      x-data x-on:submit="if (! confirm({{ Js::from('¿Quieres cancelar esta solicitud? Esta acción no se puede deshacer.') }})) $event.preventDefault()">
                    @csrf @method('PATCH')
                    <p class="text-sm text-gray-600">Puedes cancelar mientras la solicitud siga en trámite.</p>
                    <button type="submit" class="mt-3 inline-flex items-center rounded-md bg-gray-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Cancelar solicitud</button>
                </form>
            @endif
        @endcan

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
