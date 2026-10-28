@props(['titulo', 'texto' => null])
{{-- Mensaje cuando una lista no tiene resultados. --}}
<div {{ $attributes->merge(['class' => 'px-6 py-12 text-center']) }}>
    <p class="text-base font-semibold text-blue-950">{{ $titulo }}</p>
    @if ($texto)<p class="mx-auto mt-1 max-w-md text-sm text-slate-600">{{ $texto }}</p>@endif
    @if (trim((string) $slot) !== '')<div class="mt-4 flex justify-center gap-2">{{ $slot }}</div>@endif
</div>
