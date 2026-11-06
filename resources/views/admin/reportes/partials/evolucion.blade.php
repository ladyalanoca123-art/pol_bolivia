{{-- Solicitudes y adopciones de los últimos 6 meses (no depende del período). --}}
                <section aria-labelledby="titulo-evolucion" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5 lg:col-span-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 id="titulo-evolucion" class="text-lg font-semibold text-blue-950">Solicitudes y adopciones por mes</h2>
                            <p class="text-sm text-slate-500">Últimos 6 meses (no depende del período elegido)</p>
                        </div>
                        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-slate-600" aria-hidden="true">
                            <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-blue-600"></span> Solicitudes recibidas</li>
                            <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-500"></span> Adopciones</li>
                        </ul>
                    </div>

                    @if ($hayEvolucion)
                        <div class="mt-4" aria-hidden="true">
                            <div class="flex items-end justify-between gap-2 border-b border-slate-200 sm:gap-4">
                                @foreach ($evolucionMensual as $mes)
                                    <div class="flex min-w-0 flex-1 justify-center gap-1">
                                        @foreach ([['solicitudes', 'fill-blue-600'], ['adopciones', 'fill-amber-500']] as [$serie, $color])
                                            @php $alto = round($mes[$serie] / $maxEvolucion * 100, 1); @endphp
                                            <svg class="h-40 w-3 sm:w-6" viewBox="0 0 10 100" preserveAspectRatio="none">
                                                <rect x="0" y="{{ 100 - $alto }}" width="10" height="{{ $alto }}" rx="1" class="{{ $color }}"><title>{{ $mes[$serie] }}</title></rect>
                                            </svg>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-1 flex justify-between gap-2 sm:gap-4">
                                @foreach ($evolucionMensual as $mes)
                                    <div class="min-w-0 flex-1 text-center">
                                        <p class="text-xs font-medium capitalize text-slate-600">{{ $mes['etiqueta'] }}@if ($mes['en_curso'])*@endif</p>
                                        <p class="text-[0.7rem] text-slate-500"><span class="text-blue-700">{{ $mes['solicitudes'] }}</span> · <span class="text-amber-700">{{ $mes['adopciones'] }}</span></p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="mt-6 {{ $vacio }}">No hay solicitudes ni adopciones registradas en los últimos 6 meses.</p>
                    @endif

                    <details class="mt-4">
                        <summary class="{{ $enlace }} cursor-pointer">Ver como tabla</summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full min-w-[18rem] text-left text-sm">
                                <caption class="sr-only">Solicitudes recibidas y adopciones completadas por mes</caption>
                                <thead class="text-xs uppercase text-slate-500"><tr><th scope="col" class="py-1 pe-3">Mes</th><th scope="col" class="py-1 pe-3 text-right">Solicitudes</th><th scope="col" class="py-1 text-right">Adopciones</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($evolucionMensual as $mes)
                                        <tr><th scope="row" class="py-1 pe-3 font-medium capitalize text-slate-700">{{ \Illuminate\Support\Carbon::parse($mes['mes'].'-01')->translatedFormat('F Y') }}@if ($mes['en_curso']) (en curso)@endif</th><td class="py-1 pe-3 text-right">{{ $mes['solicitudes'] }}</td><td class="py-1 text-right">{{ $mes['adopciones'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <div class="mt-3 flex flex-col gap-1 border-t border-slate-100 pt-3 text-xs text-slate-500 sm:flex-row sm:justify-between">
                        <p>* Mes en curso. Adopciones concretadas por fecha de resolución; solicitudes por fecha de envío.@if ($adopcionesSinFechaResolucion > 0) El total acumulado incluye {{ $adopcionesSinFechaResolucion }} {{ $adopcionesSinFechaResolucion === 1 ? 'adopción aceptada sin fecha de resolución verificable' : 'adopciones aceptadas sin fecha de resolución verificable' }} que no aparece{{ $adopcionesSinFechaResolucion === 1 ? '' : 'n' }} en la serie mensual; no se les asigna una fecha.@endif</p>
                        @if ($conPeriodo)
                            <p class="font-medium text-slate-700">Adopciones en el período: {{ $totalAdopciones }}</p>
                        @endif
                    </div>
                </section>
