@use('App\Support\CuestionarioAdopcion', 'C')
@php
    // Los mensajes son informativos; nunca muestran comentarios internos ni resultados privados de evaluación.
    $mensajes = [
        'pendiente' => 'La fundación recibió tu solicitud y está revisando la información.',
        'en_espera' => 'Tu solicitud está en evaluación. La fundación se pondrá en contacto contigo para continuar el proceso.',
        'aprobada' => '¡Tu solicitud fue aprobada! La mascota quedó reservada para ti. La fundación te contactará por WhatsApp para coordinar la entrega.',
        'aceptada' => 'La adopción fue concretada y la entrega quedó registrada. ¡Gracias por darle un hogar!',
        'rechazada' => 'Esta solicitud no fue aprobada. Si necesitas orientación, puedes comunicarte con la fundación.',
        'cancelada' => 'Esta solicitud fue cancelada.',
        'cerrada' => 'La mascota encontró un hogar mediante otra solicitud. Gracias por tu interés.',
        'no_concretada' => 'La entrega no pudo concretarse. La fundación revisará los próximos pasos y se pondrá en contacto contigo.',
    ];
@endphp
<x-admin.pagina :titulo="$solicitud->mascota->nombre" seccion="Detalle de solicitud" ancho="max-w-4xl" :volver="route('solicitudes-adopcion.index')" volver-texto="Volver a mis solicitudes">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
            <span class="text-sm text-slate-500">Enviada el {{ $solicitud->created_at->format('d/m/Y H:i') }}</span>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">No se pudo completar la acción.</p>
            <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <x-admin.tarjeta>
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
            <x-foto-mascota :mascota="$solicitud->mascota" class="h-24 w-24 rounded-2xl" />
            <div class="min-w-0 flex-1 text-sm text-slate-700">
                <p class="text-xs text-slate-500">¿Qué sigue?</p>
                <p class="mt-1 text-base text-blue-950">{{ $mensajes[$solicitud->estado] ?? 'Consulta aquí el estado actualizado de tu solicitud.' }}</p>
                @if ($solicitud->estado === 'aprobada' && $solicitud->numero_acta)
                    <p class="mt-2 text-xs text-slate-500">Número de acta: <span class="font-semibold">{{ $solicitud->numero_acta }}</span></p>
                @endif
                @if ($solicitud->estado === 'aceptada' && $solicitud->entregada_at)
                    <p class="mt-2 text-xs text-slate-500">Fecha de entrega registrada: <span class="font-semibold">{{ $solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y') }}</span></p>
                @endif
            </div>
        </div>

        <div class="mt-6">
            <h2 class="sr-only">Progreso</h2>
            <x-progreso-solicitud :solicitud="$solicitud" />
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3">
            <x-admin.dato etiqueta="Mascota">{{ $solicitud->mascota->nombre }}</x-admin.dato>
            <x-admin.dato etiqueta="Zona aproximada">{{ $solicitud->zona_aproximada }}</x-admin.dato>
            <x-admin.dato etiqueta="Horario para la visita">{{ $solicitud->horario_preferido }}</x-admin.dato>
        </dl>

        @can('cancel', $solicitud)
            @if ($solicitud->solicitante_id === auth()->id())
                <form method="POST" action="{{ route('solicitudes-adopcion.cancel', $solicitud) }}" class="mt-6 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between"
                      x-data x-on:submit="if (! confirm({{ Js::from('¿Quieres cancelar esta solicitud? Esta acción no se puede deshacer.') }})) $event.preventDefault()">
                    @csrf @method('PATCH')
                    <p class="text-sm text-slate-600">Puedes cancelar mientras la solicitud siga en trámite.</p>
                    <x-admin.boton variante="peligro-suave" tamano="chico">Cancelar solicitud</x-admin.boton>
                </form>
            @endif
        @endcan
    </x-admin.tarjeta>

    <x-admin.tarjeta titulo="Tus respuestas">
        <div class="space-y-5">
            @foreach (C::SECCIONES as $seccion => $preguntas)
                <div>
                    <h3 class="text-sm font-semibold text-blue-950">{{ $seccion }}</h3>
                    <dl class="mt-2 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                        @foreach ($preguntas as [$num, $campo, $texto, $catalogo])
                            @php $valor = C::respuestaLegible($solicitud->respuestas, $campo, $catalogo); @endphp
                            @continue($valor === null)
                            <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2">
                                <dt class="text-slate-500">{{ $num }}. {{ $texto }}</dt>
                                <dd class="text-slate-900">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </x-admin.tarjeta>
</x-admin.pagina>
