@props(['mascotas'])
{{--
    Fila «Te esperan hace más tiempo»: mascotas disponibles con Mascota::DIAS_ESPERANDO_HOGAR días o más y sin
    solicitudes en trámite. Carrusel manual (sin movimiento automático): las flechas solo aparecen si no caben
    todas; en el celular se desliza con el dedo.
--}}
@if ($mascotas->isNotEmpty())
    <section aria-labelledby="titulo-esperan-hogar" {{ $attributes->merge(['class' => 'rounded-3xl bg-amber-50 p-4 ring-2 ring-amber-200 sm:p-6']) }}
             x-data="{ desborda: false, medir() { this.desborda = this.$refs.fila.scrollWidth > this.$refs.fila.clientWidth + 4 }, mover(lado) { this.$refs.fila.scrollBy({ left: lado * this.$refs.fila.clientWidth * 0.9, behavior: 'smooth' }) } }"
             x-init="$nextTick(() => medir())" @resize.window.debounce.150ms="medir()">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="titulo-esperan-hogar" class="flex items-center gap-2 text-xl font-extrabold text-amber-900 sm:text-2xl">
                    <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z"/></svg>
                    Te esperan hace más tiempo
                </h2>
                <p class="mt-1 text-sm text-amber-950/80 sm:text-base">Llevan más de {{ \App\Models\Mascota::DIAS_ESPERANDO_HOGAR }} días buscando un hogar. Compartir su ficha también ayuda.</p>
            </div>
            <div x-show="desborda" x-cloak class="flex shrink-0 gap-2">
                <button type="button" @click="mover(-1)" class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-lg font-bold text-blue-950 shadow ring-1 ring-amber-200 hover:bg-amber-100" aria-label="Ver anteriores">‹</button>
                <button type="button" @click="mover(1)" class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-lg font-bold text-blue-950 shadow ring-1 ring-amber-200 hover:bg-amber-100" aria-label="Ver siguientes">›</button>
            </div>
        </div>
        <div x-ref="fila" @scroll.debounce.100ms="medir()" class="mt-5 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:thin] sm:gap-6">
            @foreach ($mascotas as $mascota)
                <div class="w-[calc(50%-0.5rem)] shrink-0 snap-start lg:w-[calc(25%-1.125rem)]">
                    <x-mascota-publica-card :mascota="$mascota" :promocionar="true" />
                </div>
            @endforeach
        </div>
    </section>
@endif
