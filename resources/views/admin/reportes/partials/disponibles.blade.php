{{-- Mascotas disponibles por especie y tamaño (estado actual). --}}
                <section aria-labelledby="titulo-disponibles" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                    <h2 id="titulo-disponibles" class="text-lg font-semibold text-blue-950">Mascotas disponibles</h2>
                    <p class="text-sm text-slate-500">
                        @if ($disponibles['total'] === 0) Estado actual @else {{ $disponibles['total'] }} {{ $disponibles['total'] === 1 ? 'mascota espera' : 'mascotas esperan' }} hogar (estado actual) @endif
                    </p>
                    @if ($disponibles['total'] === 0)
                        <p class="mt-6 {{ $vacio }}">No hay mascotas disponibles en este momento.</p>
                    @else
                        @foreach ([['Por especie', $disponibles['especie'], $especies], ['Por tamaño', $disponibles['tamano'], $tamanos]] as [$grupo, $valores, $etiquetas])
                            <h3 class="mt-4 text-sm font-semibold text-slate-700">{{ $grupo }}</h3>
                            <dl class="mt-2 space-y-2">
                                @foreach ($etiquetas as $clave => $etiqueta)
                                    <div class="grid grid-cols-[5.5rem_1fr_2rem] items-center gap-3 text-sm">
                                        <dt class="text-slate-700">{{ $etiqueta }}</dt>
                                        <svg class="h-2.5 w-full" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="10" rx="5" class="fill-slate-100"/><rect width="{{ round(($valores[$clave] ?? 0) / $maxDisponibles * 100, 1) }}" height="10" rx="5" class="fill-blue-600"/></svg>
                                        <dd class="text-right font-semibold text-blue-950">{{ $valores[$clave] ?? 0 }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endforeach
                    @endif
                </section>
