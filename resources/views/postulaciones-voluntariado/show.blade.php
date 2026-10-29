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

<x-admin.pagina :titulo="'Postulación del '.$postulacion->created_at->format('d/m/Y')" seccion="Voluntariado" ancho="max-w-3xl" :volver="route('postulaciones-voluntariado.index')" volver-texto="Volver a voluntariado">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="rounded-xl border p-4 text-sm {{ $colorEstado }}">
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

        </div>
</x-admin.pagina>
