<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Voluntariado POL</h2>
        <p class="mt-1 text-sm text-gray-600">Ayuda a rescatar, cuidar y acompañar a las mascotas en adopción.</p>
    </x-slot>

    @php
        $coloresEstado = [
            'pendiente' => 'bg-amber-100 text-amber-800 ring-amber-300',
            'aprobada' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
            'rechazada' => 'bg-red-100 text-red-800 ring-red-300',
            'cancelada' => 'bg-gray-100 text-gray-700 ring-gray-300',
        ];
        // $tienePendiente llega desde PostulacionVoluntariadoController@index.
        // Solo un ciudadano puede postularse (create/store); un voluntario solo consulta.
        $puedePostularse = auth()->user()->hasRole('ciudadano');
    @endphp

    <div class="py-12"><div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($postulaciones->total() === 0)
            {{-- Estado vacío --}}
            <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-950 text-amber-300"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg></div>
                @if ($puedePostularse)
                    <h3 class="mt-4 text-lg font-bold text-blue-950">Todavía no te has postulado</h3>

                    <div class="mx-auto mt-6 max-w-md text-left">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-blue-800">¿Cómo funciona?</h4>
                        <ol class="mt-3 space-y-3">
                            @foreach ([
                                'Completa la postulación (10 preguntas, unos 5 minutos).',
                                'La fundación revisa tu información y te contacta.',
                                'Si es aprobada, te sumas al equipo de voluntarios.',
                            ] as $paso)
                                <li class="flex items-start gap-3 text-sm text-gray-700">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-400 text-xs font-extrabold text-blue-950">{{ $loop->iteration }}</span>
                                    <span>{{ $paso }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    <a href="{{ route('postulaciones-voluntariado.create') }}" class="mt-8 inline-block rounded-full bg-amber-400 px-6 py-3 font-extrabold text-blue-950 hover:bg-amber-300">Postularme como voluntario</a>
                @else
                    {{-- Voluntario: solo consulta, sin invitación a postularse --}}
                    <h3 class="mt-4 text-lg font-bold text-blue-950">No tienes postulaciones registradas</h3>
                @endif
            </div>
        @else
            {{-- Nueva postulación: solo ciudadano y si no hay una pendiente --}}
            @if ($puedePostularse)
                <div class="mb-6">
                    @if ($tienePendiente)
                        <div class="rounded-md border-l-4 border-amber-400 bg-amber-50 p-4 text-sm text-amber-900">Tu postulación está en revisión. Podrás enviar otra cuando la fundación responda.</div>
                    @else
                        <a href="{{ route('postulaciones-voluntariado.create') }}" class="inline-block rounded-full bg-amber-400 px-6 py-3 font-extrabold text-blue-950 hover:bg-amber-300">Nueva postulación</a>
                    @endif
                </div>
            @endif

            <ul class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($postulaciones as $postulacion)
                    <li class="flex gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-blue-950 text-amber-300"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg></div>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h3 class="text-base font-bold text-blue-950">Postulación del {{ $postulacion->created_at->format('d/m/Y') }}</h3>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $coloresEstado[$postulacion->estado] ?? 'bg-gray-100 text-gray-700 ring-gray-300' }}">{{ ucfirst($postulacion->estado) }}</span>
                            </div>
                            <div class="mt-auto pt-3">
                                <a href="{{ route('postulaciones-voluntariado.show', $postulacion) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline">Ver detalle <span aria-hidden="true">›</span></a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $postulaciones->links() }}</div>
        @endif
    </div></div>
</x-app-layout>
