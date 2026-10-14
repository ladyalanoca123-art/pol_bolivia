<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Detalle de postulación</h2></x-slot>

    @php
        // Color y mensaje según el estado real de la postulación.
        $estados = [
            'pendiente' => ['border-amber-300 bg-amber-50 text-amber-900', 'La fundación está revisando tu postulación. Te contactaremos pronto.'],
            'aprobada' => ['border-emerald-300 bg-emerald-50 text-emerald-900', '¡Bienvenido/a al equipo! La fundación te contactará para coordinar tu incorporación.'],
            'rechazada' => ['border-red-300 bg-red-50 text-red-900', 'Tu postulación no fue aprobada en esta ocasión. Revisa los comentarios de la fundación.'],
            'cancelada' => ['border-gray-300 bg-gray-50 text-gray-800', 'Esta postulación fue cancelada.'],
        ];
        // La bienvenida solo aplica si sigue siendo voluntario; si fue desactivado (vuelve a ciudadano), mensaje neutral.
        if (! auth()->user()->hasRole('voluntario')) {
            $estados['aprobada'][1] = 'Esta postulación fue aprobada anteriormente. Para conocer tu situación actual, contacta con la fundación.';
        }
        [$colorEstado, $mensajeEstado] = $estados[$postulacion->estado] ?? ['border-gray-300 bg-gray-50 text-gray-800', ''];
    @endphp

    <div class="py-12"><div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav aria-label="Ruta de navegación" class="mb-4 text-sm">
            <ol class="flex flex-wrap items-center gap-1 text-gray-600">
                <li><a href="{{ route('postulaciones-voluntariado.index') }}" class="font-medium text-blue-800 hover:text-blue-950 hover:underline">Voluntariado</a></li>
                <li aria-hidden="true" class="text-gray-400">›</li>
                <li aria-current="page" class="font-semibold text-blue-950">Postulación del {{ $postulacion->created_at->format('d/m/Y') }}</li>
            </ol>
        </nav>

        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
            <div class="rounded-md border p-4 text-sm {{ $colorEstado }}">
                <p class="font-semibold">Estado: {{ ucfirst($postulacion->estado) }}</p>
                @if ($mensajeEstado)<p class="mt-1">{{ $mensajeEstado }}</p>@endif
                <p class="mt-1 opacity-80">Enviada el {{ $postulacion->created_at->format('d/m/Y H:i') }}</p>
            </div>

            @if ($postulacion->comentarios_admin)
                <div class="mt-6 rounded-md border-l-4 border-amber-400 bg-amber-50 p-4 text-sm text-gray-800">
                    <h4 class="font-semibold text-blue-950">Comentarios de la fundación</h4>
                    <p class="mt-1 whitespace-pre-line">{{ $postulacion->comentarios_admin }}</p>
                </div>
            @endif

            <h3 class="mt-6 text-base font-semibold text-gray-900">Tus respuestas</h3>
            <x-postulacion-respuestas :postulacion="$postulacion" />

            <a href="{{ route('postulaciones-voluntariado.index') }}" class="mt-6 inline-block text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline"><span aria-hidden="true">‹</span> Volver a voluntariado</a>
        </div>
    </div></div>
</x-app-layout>
