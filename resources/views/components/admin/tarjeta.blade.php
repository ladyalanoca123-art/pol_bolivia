@props(['titulo' => null, 'descripcion' => null, 'relleno' => true, 'tono' => 'blanco'])
{{-- Tarjeta del panel de administración. tono: blanco, ambar (avisos) o rojo (acciones delicadas). --}}
@php
    $fondo = ['blanco' => 'border-slate-200 bg-white', 'ambar' => 'border-amber-200 bg-amber-50', 'rojo' => 'border-red-200 bg-white'][$tono] ?? 'border-slate-200 bg-white';
@endphp
<section {{ $attributes->merge(['class' => "rounded-2xl border {$fondo}"]) }}>
    @if ($titulo || isset($acciones))
        <div class="flex flex-wrap items-start justify-between gap-3 {{ $relleno ? 'px-5 pt-5 sm:px-6' : 'border-b border-slate-100 px-5 py-4 sm:px-6' }}">
            <div class="min-w-0">
                @if ($titulo)<h2 class="text-base font-semibold text-blue-950">{{ $titulo }}</h2>@endif
                @if ($descripcion)<p class="mt-0.5 text-sm text-slate-600">{{ $descripcion }}</p>@endif
            </div>
            {{ $acciones ?? '' }}
        </div>
    @endif
    <div class="{{ $relleno ? 'px-5 pb-5 sm:px-6 '.($titulo || isset($acciones) ? 'pt-4' : 'pt-5') : '' }}">{{ $slot }}</div>
</section>
