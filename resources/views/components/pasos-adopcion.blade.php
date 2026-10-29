@props(['compacto' => false])
{{--
    Los 4 pasos para adoptar, tal como funciona el sistema: solicitud, evaluación por WhatsApp,
    reserva y entrega, y 2 visitas de seguimiento. compacto: lista vertical para la ficha de una mascota.
--}}
@php
    $pasos = [
        ['Elige', 'Conoce a las mascotas y revisa su perfil.'],
        ['Envía tu solicitud', 'Crea tu cuenta y responde un cuestionario breve.'],
        ['Te contactamos', 'La fundación revisa tu solicitud y conversa contigo por WhatsApp.'],
        ['Llévala a casa', 'Coordinamos la entrega y te acompañamos con 2 visitas de seguimiento.'],
    ];
@endphp
@if ($compacto)
    <ol {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @foreach ($pasos as [$titulo, $texto])
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-950 text-xs font-bold text-amber-300">{{ $loop->iteration }}</span>
                <p class="text-sm text-slate-600"><span class="font-semibold text-blue-950">{{ $titulo }}.</span> {{ $texto }}</p>
            </li>
        @endforeach
    </ol>
@else
    <ol {{ $attributes->merge(['class' => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4']) }}>
        @foreach ($pasos as [$titulo, $texto])
            <li class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-amber-300">{{ $loop->iteration }}</span>
                <h3 class="mt-4 text-lg font-bold text-blue-950">{{ $titulo }}</h3>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $texto }}</p>
            </li>
        @endforeach
    </ol>
@endif
