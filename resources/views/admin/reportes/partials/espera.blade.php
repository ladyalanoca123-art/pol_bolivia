{{-- Mascotas que esperan hogar hace más tiempo (estado actual). --}}
                <section aria-labelledby="titulo-espera" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5 md:col-span-2 lg:col-span-1">
                    <h2 id="titulo-espera" class="text-lg font-semibold text-blue-950">Esperan hogar hace más tiempo</h2>
                    <p class="text-sm text-slate-500">Días desde su registro; ideal para darles difusión</p>
                    @if ($esperanMasTiempo->isEmpty())
                        <p class="mt-6 {{ $vacio }}">No hay mascotas disponibles en este momento.</p>
                    @else
                        <ul class="mt-3 divide-y divide-slate-100">
                            @foreach ($esperanMasTiempo as ['mascota' => $m, 'dias' => $espera])
                                <li class="flex items-center gap-3 py-2.5">
                                    <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xl">{{ $m->especie === 'gato' ? '🐱' : '🐶' }}</span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('admin.mascotas.show', $m) }}" class="font-semibold text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">{{ $m->nombre }}</a>
                                        <p class="truncate text-sm text-slate-500">{{ $m->especie === 'gato' ? 'Gato' : 'Perro' }} · {{ mb_strtolower($tamanos[$m->tamano] ?? $m->tamano) }} · {{ $edad($m) }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold text-amber-700">{{ $espera }} {{ $espera === 1 ? 'día' : 'días' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
