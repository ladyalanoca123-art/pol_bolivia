<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Mis solicitudes de adopción</h2></x-slot>

    @php
        $coloresEstado = [
            'pendiente' => 'bg-amber-100 text-amber-800 ring-amber-300',
            'en_espera' => 'bg-blue-100 text-blue-900 ring-blue-300',
            'aprobada' => 'bg-indigo-100 text-indigo-900 ring-indigo-300',
            'aceptada' => 'bg-green-100 text-green-900 ring-green-300',
            'rechazada' => 'bg-red-100 text-red-800 ring-red-300',
            'cancelada' => 'bg-gray-100 text-gray-700 ring-gray-300',
            'cerrada' => 'bg-gray-100 text-gray-700 ring-gray-300',
            'no_concretada' => 'bg-amber-100 text-amber-900 ring-amber-300',
        ];
        $mensajesEstado = [
            'pendiente' => 'La fundación está revisando tu solicitud.',
            'en_espera' => 'Tu solicitud está en evaluación.',
            'aprobada' => 'Fue aprobada; la mascota está reservada mientras se coordina la entrega.',
            'aceptada' => 'La adopción fue concretada.',
            'rechazada' => 'Esta solicitud no fue aprobada.',
            'cancelada' => 'Esta solicitud fue cancelada.',
            'cerrada' => 'La mascota encontró hogar mediante otra solicitud.',
            'no_concretada' => 'La entrega no pudo concretarse; la fundación se pondrá en contacto contigo.',
        ];
    @endphp

    <div class="py-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if (session('status'))<div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-700" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if ($solicitudes->total() === 0)
            <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-950 text-amber-300" aria-hidden="true">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg>
                </div>
                @if (auth()->user()->hasRole('ciudadano'))
                    <h3 class="mt-4 text-lg font-bold text-blue-950">Todavía no tienes solicitudes de adopción</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-600">Cuando envíes una solicitud para adoptar una mascota, aparecerá aquí junto con su estado.</p>
                    <a href="{{ route('mascotas.catalogo') }}" class="mt-6 inline-block rounded-full bg-amber-400 px-6 py-3 font-extrabold text-blue-950 hover:bg-amber-300">Ver mascotas disponibles</a>
                @else
                    <h3 class="mt-4 text-lg font-bold text-blue-950">No tienes solicitudes de adopción registradas</h3>
                @endif
            </div>
        @else
            <ul class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($solicitudes as $solicitud)
                    @php
                        $fotos = $solicitud->mascota->fotos;
                        $fotoPrincipal = $fotos->firstWhere('es_principal', true) ?? $fotos->first();
                    @endphp
                    <li class="flex gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        @if ($fotoPrincipal)
                            <img src="{{ $fotoPrincipal->url_miniatura }}" alt="Foto de {{ $solicitud->mascota->nombre }}" class="h-20 w-20 shrink-0 rounded-xl object-cover sm:h-24 sm:w-24">
                        @else
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-blue-950 text-amber-300 sm:h-24 sm:w-24" role="img" aria-label="{{ $solicitud->mascota->nombre }} todavía no tiene foto">
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg>
                            </div>
                        @endif

                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h3 class="truncate text-lg font-bold text-blue-950">{{ $solicitud->mascota->nombre }}</h3>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $coloresEstado[$solicitud->estado] ?? 'bg-gray-100 text-gray-700 ring-gray-300' }}">{{ $solicitud->estadoLegible() }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">Enviada el {{ $solicitud->created_at->format('d/m/Y H:i') }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $mensajesEstado[$solicitud->estado] ?? 'Consulta el detalle para ver el estado de tu solicitud.' }}</p>
                            <div class="mt-auto pt-3">
                                <a href="{{ route('solicitudes-adopcion.show', $solicitud) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline">Ver detalle <span aria-hidden="true">›</span></a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($solicitudes->hasPages())<div class="mt-6">{{ $solicitudes->links() }}</div>@endif
        @endif
    </div></div>
</x-app-layout>
