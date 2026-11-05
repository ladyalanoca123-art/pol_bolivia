@props(['tono' => 'gris', 'punto' => true])
{{--
    Etiqueta de estado. tono: verde, ambar, azul, indigo, rojo, violeta o gris.
    Con punto (por defecto) lleva un ícono según el tono, para que el estado se reconozca también sin distinguir colores:
    verde ✓ (positivo), ámbar reloj (en espera), rojo ✕ (rechazo o problema), azul e índigo círculo con punto
    (en curso), violeta información y gris guion (cerrado o inactivo).
--}}
@php
    [$fondo] = [
        'verde' => ['bg-emerald-50 text-emerald-800 ring-emerald-300'],
        'ambar' => ['bg-amber-50 text-amber-900 ring-amber-300'],
        'azul' => ['bg-sky-50 text-sky-900 ring-sky-300'],
        'indigo' => ['bg-indigo-50 text-indigo-900 ring-indigo-300'],
        'rojo' => ['bg-red-50 text-red-800 ring-red-300'],
        'violeta' => ['bg-violet-50 text-violet-900 ring-violet-300'],
        'gris' => ['bg-slate-100 text-slate-700 ring-slate-300'],
    ][$tono] ?? ['bg-slate-100 text-slate-700 ring-slate-300'];
    $icono = [
        'verde' => 'm5 12.5 4.5 4.5L19 7.5',
        'ambar' => 'M12 7.5V12l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'azul' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 12h.01',
        'indigo' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 12h.01',
        'rojo' => 'M7 7l10 10M17 7 7 17',
        'violeta' => 'M12 16v-4.5M12 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'gris' => 'M6 12h12',
    ][$tono] ?? 'M6 12h12';
@endphp
<span {{ $attributes->merge(['class' => "insignia inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-[13px] font-semibold leading-none ring-1 ring-inset {$fondo}"]) }}>
    @if ($punto)<svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icono }}" /></svg>@endif{{ $slot }}
</span>
