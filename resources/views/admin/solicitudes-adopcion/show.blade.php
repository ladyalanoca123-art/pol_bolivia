@use('App\Support\CuestionarioAdopcion', 'C')
@php
    $alertas = collect($solicitud->alertas ?? []);
    $alertaPorPregunta = $alertas->groupBy('pregunta')->map(fn ($g) => $g->contains('nivel', 'rojo') ? 'rojo' : 'amarillo');
    $etapas = [
        1 => ['Recibida', 'Revisión inicial'],
        2 => ['En evaluación', 'Revisión y contacto externo'],
        3 => ['Reservada', 'Esperando la entrega'],
        4 => ['Adopción concretada', 'Entrega registrada'],
    ];
    $paso = $solicitud->etapaAlcanzada();
    $etapaFinalSinAdopcion = $solicitud->terminoSinAdopcion();
    $mensajesFinales = [
        'rechazada' => $solicitud->comentarios_admin,
        'cancelada' => $solicitud->motivo_cancelacion,
        'cerrada' => 'La mascota fue adoptada mediante otra solicitud.',
        'no_concretada' => $solicitud->motivo_no_concretada,
    ];
    $accion = 'rounded-xl border p-4 sm:p-5';
@endphp
<x-admin.pagina :titulo="$solicitud->solicitante->nombres.' '.$solicitud->solicitante->apellidos" seccion="Detalle de solicitud de adopción"
                :volver="route('admin.solicitudes-adopcion.index')" volver-texto="Volver al listado">
    <x-slot name="subtitulo">
        {{-- Quién quiere adoptar a quién: la mascota con su foto, junto al estado y el nivel de alerta de la solicitud. --}}
        @php $fotoMascota = $solicitud->mascota->fotos->firstWhere('es_principal', true) ?? $solicitud->mascota->fotos->first(); @endphp
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-3">
            <a href="{{ route('admin.mascotas.show', $solicitud->mascota) }}" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white py-2 pe-4 ps-2 shadow-sm hover:border-blue-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                @if ($fotoMascota)
                    <img src="{{ $fotoMascota->url_miniatura }}" alt="" class="h-12 w-12 shrink-0 rounded-xl bg-slate-100 object-cover">
                @else
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl" aria-hidden="true">{{ $solicitud->mascota->especie === 'gato' ? '🐱' : '🐶' }}</span>
                @endif
                <span class="min-w-0">
                    <span class="block text-[13px] text-slate-500">Quiere adoptar a</span>
                    <span class="block text-base font-bold text-blue-950 group-hover:underline">{{ $solicitud->mascota->nombre }}</span>
                </span>
            </a>
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
                <x-nivel-alerta :nivel="$solicitud->nivel_alerta" />
            </div>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Revisa la información enviada:</p>
            <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Etapas: franja azul con el avance (completada ✓ verde, actual en ámbar, pendiente tenue) --}}
    <section class="rounded-2xl bg-gradient-to-r from-blue-950 to-blue-900 px-4 py-5 shadow-sm sm:px-6">
        <h2 class="sr-only">Etapas de la solicitud</h2>
        <ol class="grid grid-cols-2 gap-x-3 gap-y-5 lg:grid-cols-4" aria-label="Etapas de la solicitud">
            @foreach ($etapas as $i => [$titulo, $texto])
                @php
                    $completada = $i < $paso || ($i === 4 && $solicitud->estado === 'aceptada');
                    $actual = ! $completada && $i === $paso;
                    $circulo = match (true) {
                        $completada => 'bg-emerald-400 text-blue-950',
                        $actual && $etapaFinalSinAdopcion => 'bg-orange-300 text-blue-950 ring-4 ring-orange-300/30',
                        $actual => 'bg-amber-400 text-blue-950 ring-4 ring-amber-400/30',
                        default => 'border-2 border-white/30 text-blue-200',
                    };
                @endphp
                <li class="flex items-start gap-3" @if ($actual) aria-current="step" @endif>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-base font-bold {{ $circulo }}" aria-hidden="true">
                        @if ($completada)
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                        @else
                            {{ $i }}
                        @endif
                    </span>
                    <span class="min-w-0">
                        {{-- El número va en el círculo (o un ✓): el lector de pantalla recibe «3 · Reservada» completo. --}}
                        <span class="sr-only">{{ $i }} · {{ $titulo }}</span>
                        <span class="block text-[15px] font-semibold leading-tight {{ $completada || $actual ? 'text-white' : 'text-blue-200' }}" aria-hidden="true">{{ $titulo }}</span>
                        <span class="mt-0.5 block text-sm {{ $actual ? 'text-amber-200' : 'text-blue-200' }}">
                            {{ $actual ? ($etapaFinalSinAdopcion ? 'Se detuvo aquí' : 'Etapa actual') : $texto }}
                        </span>
                        <span class="sr-only">{{ $completada ? '(completada)' : ($actual ? '' : '(pendiente)') }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Columna principal --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">

            {{-- Acciones de la etapa actual --}}
            <x-admin.tarjeta titulo="Próximo paso">
                @if (in_array($solicitud->estado, ['rechazada', 'cancelada', 'cerrada', 'no_concretada'], true))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <p class="font-semibold">{{ $solicitud->estadoLegible() }}</p>
                        @if (filled($mensajesFinales[$solicitud->estado] ?? null))<p class="mt-1 whitespace-pre-line">{{ $mensajesFinales[$solicitud->estado] }}</p>@endif
                        @if ($solicitud->resuelta_at)<p class="mt-2 text-xs text-amber-800">Registrada el {{ $solicitud->resuelta_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>@endif
                    </div>
                @endif

                <div class="space-y-4">
                    @if ($solicitud->estado === 'pendiente')
                        @can('evaluate', $solicitud)
                            <form method="POST" action="{{ route('admin.solicitudes-adopcion.evaluate', $solicitud) }}" class="{{ $accion }} border-blue-200 bg-blue-50/50">
                                @csrf @method('PATCH')
                                <h3 class="text-[15px] font-semibold text-blue-950">Pasar a evaluación</h3>
                                <p class="admin-ayuda">La mascota seguirá disponible durante la evaluación.</p>
                                <details class="mt-2" @if (filled(old('comentarios_admin')) || $errors->has('comentarios_admin')) open @endif>
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">+ Agregar comentario (opcional)</summary>
                                    <label for="comentarios-evaluar" class="sr-only">Comentario interno</label>
                                    <textarea id="comentarios-evaluar" name="comentarios_admin" rows="2" maxlength="1000" class="admin-campo" placeholder="Comentario interno (queda en el historial)">{{ old('comentarios_admin') }}</textarea>
                                    <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                                </details>
                                <x-admin.boton class="mt-3">Pasar a evaluación</x-admin.boton>
                            </form>
                        @endcan
                    @endif

                    @if ($solicitud->estado === 'en_espera')
                        @if ($solicitud->mascota->estado !== 'disponible')
                            <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">La mascota ya no está disponible para aprobación.</p>
                        @endif
                        @can('approve', $solicitud)
                            <form method="POST" action="{{ route('admin.solicitudes-adopcion.approve', $solicitud) }}" class="{{ $accion }} border-emerald-200 bg-emerald-50/60"
                                  x-data x-on:submit="if (! confirm({{ Js::from('¿Aprobar la solicitud y reservar ' . $solicitud->mascota->nombre . '? La adopción se concretará después de registrar la entrega y confirmar la firma del compromiso.') }})) $event.preventDefault()">
                                @csrf @method('PATCH')
                                <h3 class="text-sm font-semibold text-emerald-950">Aprobar y reservar</h3>
                                <p class="admin-ayuda">Cuando ya hablaste con el solicitante por teléfono o WhatsApp, aprueba para reservar la mascota. La fecha se registra recién en la entrega.</p>
                                <details class="mt-2" @if (filled(old('comentarios_admin')) || $errors->has('comentarios_admin')) open @endif>
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">+ Agregar comentario (opcional)</summary>
                                    <label for="comentarios-aprobar" class="sr-only">Comentario para el equipo</label>
                                    <textarea id="comentarios-aprobar" name="comentarios_admin" rows="2" maxlength="1000" class="admin-campo" placeholder="Ej. Hablé por WhatsApp: vive en casa con patio y toda la familia está de acuerdo.">{{ old('comentarios_admin') }}</textarea>
                                    <p class="admin-ayuda">Queda en el historial con tu nombre y la fecha, para que los demás administradores sepan por qué se aprobó.</p>
                                </details>
                                <x-admin.boton variante="exito" class="mt-3">Aprobar solicitud</x-admin.boton>
                            </form>
                        @endcan
                    @endif

                    @if ($solicitud->estado === 'aprobada')
                        @can('deliver', $solicitud)
                            <form method="POST" action="{{ route('admin.solicitudes-adopcion.deliver', $solicitud) }}" class="{{ $accion }} border-emerald-200 bg-emerald-50/60"
                                  x-data x-on:submit="if (! confirm({{ Js::from('¿Confirmas que la entrega se concretó? Se marcará la mascota como adoptada y se creará el seguimiento.') }})) $event.preventDefault()">
                                @csrf @method('PATCH')
                                <h3 class="text-sm font-semibold text-emerald-950">Registrar entrega y concretar adopción</h3>
                                <div class="mt-3 max-w-xs">
                                    <label for="fecha-entrega-real" class="admin-etiqueta">Fecha real de entrega</label>
                                    <input id="fecha-entrega-real" name="fecha_entrega" type="date" min="{{ $solicitud->aprobada_at?->timezone('America/La_Paz')->toDateString() }}" max="{{ now('America/La_Paz')->toDateString() }}" required value="{{ old('fecha_entrega') }}" class="admin-campo">
                                    <x-input-error class="mt-1" :messages="$errors->get('fecha_entrega')" />
                                </div>
                                <details class="mt-3" @if (filled(old('comentarios_admin')) || $errors->has('comentarios_admin')) open @endif>
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">+ Agregar comentario (opcional)</summary>
                                    <label for="comentarios-entrega" class="sr-only">Comentario interno</label>
                                    <textarea id="comentarios-entrega" name="comentarios_admin" rows="2" maxlength="1000" class="admin-campo" placeholder="Comentario interno (queda en el historial)">{{ old('comentarios_admin') }}</textarea>
                                    <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                                </details>
                                <label class="mt-4 flex items-start gap-2 rounded-lg bg-white p-3 text-sm text-slate-800 ring-1 ring-emerald-200"><input type="checkbox" name="compromiso_firmado" value="1" required @checked(old('compromiso_firmado')) class="mt-0.5 rounded border-slate-300 text-blue-900 focus:ring-blue-900"><span>Confirmo que el adoptante firmó el compromiso de adopción.</span></label>
                                <x-input-error class="mt-1" :messages="$errors->get('compromiso_firmado')" />
                                <x-admin.boton variante="exito" class="mt-3">Registrar entrega y finalizar adopción</x-admin.boton>
                            </form>
                        @endcan
                    @endif

                    @if ($solicitud->estado === 'aceptada')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950"><p class="font-semibold">Adopción concretada</p><p class="mt-1">La entrega quedó registrada y la mascota fue marcada como adoptada.</p>
                            @if ($solicitud->seguimiento)<x-admin.boton variante="secundario" tamano="chico" class="mt-3" :href="route('admin.seguimientos.show', $solicitud->seguimiento)">Abrir seguimiento post-adopción</x-admin.boton>@else<p class="mt-2 text-amber-800">Todavía no hay un seguimiento asociado.</p>@endif
                        </div>
                    @endif

                    {{-- Acciones para cerrar sin adopción, separadas de la acción principal --}}
                    @if (in_array($solicitud->estado, ['pendiente', 'en_espera'], true))
                        @can('reject', $solicitud)
                            <details class="group rounded-xl border border-red-200" @if ($errors->has('comentarios_admin') && old('_rechazo')) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-red-800">Rechazar solicitud <span aria-hidden="true" class="text-red-400 group-open:rotate-180">▾</span></summary>
                                <form method="POST" action="{{ route('admin.solicitudes-adopcion.reject', $solicitud) }}" class="border-t border-red-100 px-4 pb-4 pt-3"
                                      x-data x-on:submit="if (! confirm({{ Js::from('¿Confirmas que deseas rechazar esta solicitud? Esta decisión no se puede revertir.') }})) $event.preventDefault()">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="_rechazo" value="1">
                                    <label for="comentarios-rechazar" class="admin-etiqueta">Motivo</label>
                                    <textarea id="comentarios-rechazar" name="comentarios_admin" rows="2" minlength="5" maxlength="1000" required class="admin-campo" placeholder="Escribe el motivo (al menos 5 caracteres).">{{ old('comentarios_admin') }}</textarea>
                                    <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                                    <x-admin.boton variante="peligro" class="mt-3">Rechazar</x-admin.boton>
                                </form>
                            </details>
                        @endcan
                    @endif

                    @if ($solicitud->estado === 'aprobada')
                        @can('markNotDelivered', $solicitud)
                            <details class="group rounded-xl border border-amber-200" @if ($errors->has('motivo_no_concretada')) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-amber-900">Marcar entrega no concretada <span aria-hidden="true" class="text-amber-500 group-open:rotate-180">▾</span></summary>
                                <form method="POST" action="{{ route('admin.solicitudes-adopcion.not-delivered', $solicitud) }}" class="border-t border-amber-100 px-4 pb-4 pt-3"
                                      x-data x-on:submit="if (! confirm({{ Js::from('¿Marcar la entrega como no concretada? ' . $solicitud->mascota->nombre . ' volverá a estar disponible.') }})) $event.preventDefault()">
                                    @csrf @method('PATCH')
                                    <label for="motivo-no-concretada" class="admin-etiqueta">Motivo</label>
                                    <textarea id="motivo-no-concretada" name="motivo_no_concretada" rows="2" minlength="5" maxlength="1000" required class="admin-campo" placeholder="Indica el motivo (al menos 5 caracteres).">{{ old('motivo_no_concretada') }}</textarea>
                                    <x-input-error class="mt-1" :messages="$errors->get('motivo_no_concretada')" />
                                    <x-admin.boton variante="secundario" class="mt-3">Registrar entrega no concretada</x-admin.boton>
                                </form>
                            </details>
                        @endcan
                    @endif

                    @if ($solicitud->estado === 'rechazada')<p class="text-sm text-slate-600">Esta solicitud fue rechazada y ya no admite cambios.</p>@elseif ($solicitud->estado === 'cancelada')<p class="text-sm text-slate-600">Esta solicitud fue cancelada y ya no admite cambios.</p>@elseif ($solicitud->estado === 'cerrada')<p class="text-sm text-slate-600">Esta solicitud se cerró porque la mascota fue adoptada mediante otra solicitud.</p>@elseif ($solicitud->estado === 'no_concretada')<p class="text-sm text-slate-600">La entrega no se concretó; la mascota volvió a estar disponible.</p>@endif
                </div>
            </x-admin.tarjeta>

            {{-- Alertas --}}
            <x-admin.tarjeta titulo="Respuestas que necesitan revisión" :tono="$alertas->contains('nivel', 'rojo') ? 'rojo' : ($alertas->isNotEmpty() ? 'ambar' : 'blanco')">
                <x-slot name="acciones">
                    <div class="flex flex-wrap gap-2">
                        <x-admin.insignia tono="rojo">{{ $alertas->where('nivel', 'rojo')->count() }} incompatibles</x-admin.insignia>
                        <x-admin.insignia tono="ambar">{{ $alertas->where('nivel', 'amarillo')->count() }} a revisar</x-admin.insignia>
                    </div>
                </x-slot>
                @if ($alertas->isEmpty())
                    <p class="text-sm text-slate-600">Ninguna respuesta requiere atención especial.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($alertas as $alerta)
                            <li class="rounded-xl border-l-4 px-3 py-2 text-sm {{ $alerta['nivel'] === 'rojo' ? 'border-red-500 bg-red-50 text-red-900' : 'border-amber-400 bg-amber-50 text-amber-900' }}">
                                <span class="block text-xs font-bold uppercase tracking-wider">{{ $alerta['nivel'] === 'rojo' ? 'Condición incompatible' : 'Requiere revisión' }} · Pregunta {{ $alerta['pregunta'] }}</span>
                                {{ $alerta['mensaje'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-xs text-slate-500">Las alertas orientan la revisión del formulario; la decisión final corresponde a la administradora.</p>
            </x-admin.tarjeta>

            {{-- Respuestas --}}
            {{-- Respuestas: fondo celeste, una tarjeta blanca por sección y cada respuesta en su recuadro --}}
            @php
                $recuadro = 'min-w-0 rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-200';
                $pregunta = 'text-[15px] font-semibold leading-snug text-slate-800';
                $respuesta = 'mt-1 break-words text-base font-bold text-slate-950';
                // Alertas por sección: con alertas, de entrada se abren solo esas secciones; sin alertas, todas.
                $alertasPorSeccion = collect(C::SECCIONES)->values()->map(fn ($preguntas) => collect($preguntas)->filter(fn ($p) => isset($alertaPorPregunta[$p[0]]))->count());
                $abiertas = $alertasPorSeccion->map(fn (int $n) => $alertas->isEmpty() || $n > 0)->all();
            @endphp
            <x-admin.tarjeta titulo="Respuestas del cuestionario" descripcion="Lo que respondió el solicitante, por secciones. Las respuestas con alerta van marcadas en rojo o ámbar." tono="celeste"
                             x-data="{ soloAlertas: false, abiertas: {{ Js::from($abiertas) }}, todas(valor) { this.abiertas = this.abiertas.map(() => valor); } }">
                <x-slot name="acciones">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($alertas->isNotEmpty())
                            <button type="button" @click="soloAlertas = !soloAlertas" :aria-pressed="soloAlertas.toString()" aria-pressed="false"
                                    :class="soloAlertas ? '!border-red-600 !bg-red-600 !text-white' : ''" class="admin-boton-borde">
                                <span x-text="soloAlertas ? 'Ver todas las respuestas' : 'Ver solo respuestas con alerta'">Ver solo respuestas con alerta</span>
                            </button>
                        @endif
                        <button type="button" x-show="!soloAlertas" @click="todas(! abiertas.every(Boolean))" class="admin-boton-borde">
                            <span x-text="abiertas.every(Boolean) ? 'Cerrar todas' : 'Abrir todas'">Cerrar todas</span>
                        </button>
                    </div>
                </x-slot>
                <div class="space-y-4">
                    @foreach (C::SECCIONES as $seccion => $preguntas)
                        @php $i = $loop->index; $conAlertas = $alertasPorSeccion[$i]; @endphp
                        <section class="overflow-hidden rounded-xl border border-sky-100 bg-white shadow-sm" x-show="! soloAlertas || {{ $conAlertas > 0 ? 'true' : 'false' }}">
                            <h3>
                                <button type="button" @click="abiertas[{{ $i }}] = ! abiertas[{{ $i }}]" :disabled="soloAlertas"
                                        :aria-expanded="(abiertas[{{ $i }}] || soloAlertas).toString()" aria-expanded="true" aria-controls="seccion-cuestionario-{{ $i }}"
                                        class="flex w-full items-center gap-2.5 bg-sky-100/60 px-4 py-2.5 text-left text-[15px] font-semibold text-blue-950 hover:bg-sky-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-950 text-[13px] font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                                    <span class="flex-1">{{ $seccion }}</span>
                                    @if ($conAlertas > 0)
                                        <x-admin.insignia tono="rojo">{{ $conAlertas }} {{ $conAlertas === 1 ? 'alerta' : 'alertas' }}</x-admin.insignia>
                                    @endif
                                    <svg class="h-5 w-5 shrink-0 text-blue-900 transition" :class="(abiertas[{{ $i }}] || soloAlertas) ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd" /></svg>
                                </button>
                            </h3>
                            <dl id="seccion-cuestionario-{{ $i }}" x-show="abiertas[{{ $i }}] || soloAlertas" class="grid grid-cols-1 gap-3 border-t border-sky-100 p-4 sm:grid-cols-2">
                                @foreach ($preguntas as [$num, $campo, $texto, $catalogo])
                                    @php $valor = C::respuestaLegible($solicitud->respuestas, $campo, $catalogo); @endphp
                                    @continue($valor === null)
                                    @php $nivel = $alertaPorPregunta[$num] ?? null; @endphp
                                    <div x-show="! soloAlertas || {{ $nivel ? 'true' : 'false' }}" class="{{ $recuadro }} {{ $nivel === 'rojo' ? '!bg-red-50 !ring-2 !ring-red-300' : ($nivel ? '!bg-amber-50 !ring-2 !ring-amber-300' : '') }}">
                                        <dt class="{{ $pregunta }}"><span class="font-bold text-blue-800">{{ $num }}.</span> {{ $texto }}</dt>
                                        <dd class="{{ $respuesta }}">
                                            {{ $valor }}
                                            @if ($nivel)
                                                <x-admin.insignia :tono="$nivel === 'rojo' ? 'rojo' : 'ambar'" class="ms-1 align-middle">{{ $nivel === 'rojo' ? 'Incompatible' : 'Revisar' }}</x-admin.insignia>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                                @if ($seccion === 'Visita')
                                    <div x-show="! soloAlertas" class="{{ $recuadro }}"><dt class="{{ $pregunta }}"><span class="font-bold text-blue-800">21.</span> Zona aproximada</dt><dd class="{{ $respuesta }}">{{ $solicitud->zona_aproximada }}</dd></div>
                                    <div x-show="! soloAlertas" class="{{ $recuadro }}"><dt class="{{ $pregunta }}"><span class="font-bold text-blue-800">23–26.</span> Consentimientos obligatorios</dt><dd class="{{ $respuesta }}">Aceptados ({{ in_array($solicitud->version_cuestionario, [null, 'a2-v2'], true) ? 'visita previa' : 'contacto por WhatsApp o llamada' }}, seguimiento, no ceder y declaración de veracidad)</dd></div>
                                @endif
                            </dl>
                        </section>
                    @endforeach
                </div>
            </x-admin.tarjeta>
        </div>

        {{--
            Columna lateral: en celular va primero (contacto y datos del solicitante a la mano). En computadora queda fija
            al bajar por el cuestionario; si es más alta que la pantalla, se desplaza por dentro con una barra fina.
        --}}
        <div class="columna-fija order-first min-w-0 space-y-6 lg:sticky lg:top-24 lg:order-none lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto lg:pe-1">
            {{-- 1. Contacto: arriba, a la altura de «Próximo paso». --}}
            @can('contact', $solicitud)
                <section class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm sm:p-6" aria-labelledby="titulo-contacto">
                    <h2 id="titulo-contacto" class="flex items-center gap-2 text-lg font-semibold text-green-900">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.8s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.3.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1 1 2 1.3 2.3 1.4.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.6-.2 1.2z"/></svg>
                        Contactar al solicitante
                    </h2>
                    <p class="mt-1 text-[15px] text-green-900">Teléfono: {{ $solicitud->solicitante->telefono ?: 'sin teléfono' }}</p>
                    <div class="mt-4">
                        <x-botones-contacto
                            :telefono="$solicitud->solicitante->telefono"
                            :mensaje="$solicitud->estado"
                            :datos="['nombre' => \App\Support\ContactoTelefonico::primerNombre($solicitud->solicitante->nombres), 'mascota' => $solicitud->mascota->nombre]"
                        />
                    </div>
                    <p class="mt-3 text-sm text-green-900/80">El mensaje se coordina fuera del sistema; no hace falta registrar entrevista ni visita previa.</p>
                </section>
            @endcan

            {{-- 2. Acta de adopción: el PDF en rojo para encontrarlo de inmediato. --}}
            @if (in_array($solicitud->estado, ['aprobada', 'aceptada'], true) || $solicitud->compromiso_foto_path)
                <x-admin.tarjeta titulo="Acta de adopción">
                    @can('viewCommitment', $solicitud)
                        <a href="{{ route('admin.solicitudes-adopcion.commitment-pdf', $solicitud) }}"
                           class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-center text-[15px] font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M12 11v6m0 0-2.5-2.5M12 17l2.5-2.5" /></svg>
                            Descargar acta de adopción (PDF)
                        </a>
                        <p class="mt-2 text-sm text-slate-600">Para imprimir, firmar y sellar.</p>

                        {{-- Debajo del rojo: verde si el acta firmada ya se subió (señal de que está lista), aviso si falta. --}}
                        @if ($solicitud->compromiso_foto_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($solicitud->compromiso_foto_path))
                            @can('viewCommitmentEvidence', $solicitud)
                                <a href="{{ route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud) }}"
                                   class="mt-3 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-center text-[15px] font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                                    Ver foto del acta firmada
                                </a>
                                <p class="mt-2 text-sm text-emerald-800">El acta firmada y sellada ya está subida.</p>
                            @endcan
                        @elseif (in_array($solicitud->estado, ['aprobada', 'aceptada'], true))
                            <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2.5 text-sm font-medium text-amber-900 ring-1 ring-inset ring-amber-200">Falta subir la foto del acta firmada y sellada (más abajo).</p>
                        @endif
                    @endcan

                    @if (in_array($solicitud->estado, ['aprobada', 'aceptada'], true))
                        <dl class="mt-4 space-y-3">
                            @if ($solicitud->numero_acta)<x-admin.dato etiqueta="Número de acta"><span class="font-semibold text-blue-950">{{ $solicitud->numero_acta }}</span></x-admin.dato>@endif
                        </dl>
                    @endif

                    @can('viewCommitment', $solicitud)
                        @can('uploadCommitmentEvidence', $solicitud)
                            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud) }}" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
                                @csrf
                                <label for="evidencia-compromiso" class="block text-sm font-semibold text-blue-950">{{ $solicitud->compromiso_foto_path ? 'Reemplazar la foto del acta firmada' : 'Subir la foto del acta firmada' }} (JPG, PNG o PDF; máximo 10 MB)</label>
                                <input id="evidencia-compromiso" name="evidencia_compromiso" type="file" accept="image/jpeg,image/png,application/pdf" required class="admin-archivo mt-2">
                                <x-input-error class="mt-1" :messages="$errors->get('evidencia_compromiso')" />
                                <p class="admin-ayuda">Se guarda de forma privada; solo la administración puede verla.</p>
                                <x-admin.boton variante="secundario" tamano="chico" class="mt-3">Guardar foto del acta</x-admin.boton>
                            </form>
                        @endcan
                    @endcan

                    @if (! in_array($solicitud->estado, ['aprobada', 'aceptada'], true) && $solicitud->compromiso_foto_path)
                        @can('viewCommitmentEvidence', $solicitud)
                            <p class="text-sm"><a href="{{ route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud) }}" class="font-semibold text-blue-900 underline">Ver foto del acta firmada</a></p>
                        @endcan
                    @endif
                </x-admin.tarjeta>
            @endif

            {{-- 3. Datos del solicitante --}}
            <x-admin.tarjeta titulo="Solicitante">
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-400 text-lg font-bold text-blue-950" aria-hidden="true">{{ mb_strtoupper(mb_substr($solicitud->solicitante->nombres, 0, 1)) }}</span>
                    <p class="min-w-0 text-base font-semibold leading-snug text-blue-950">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</p>
                </div>
                <dl class="space-y-3">
                    <x-admin.dato etiqueta="Correo electrónico">{{ $solicitud->solicitante->email }}</x-admin.dato>
                    <x-admin.dato etiqueta="Teléfono">{{ $solicitud->solicitante->telefono ?: '—' }}</x-admin.dato>
                    <x-admin.dato etiqueta="Zona aproximada">{{ $solicitud->zona_aproximada }}</x-admin.dato>
                    <x-admin.dato etiqueta="Fecha de solicitud">{{ $solicitud->created_at->format('d/m/Y H:i') }}</x-admin.dato>
                </dl>
            </x-admin.tarjeta>

            <x-admin.tarjeta titulo="Historial de la solicitud">
                <ol class="space-y-4 border-l-2 border-slate-200 ps-4 text-sm">
                    @forelse ($historial as $registro)
                        @php
                            $nuevos = $registro->valores_nuevos ?? [];
                            $anteriores = $registro->valores_anteriores ?? [];
                            $legible = fn ($e) => \App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES[$e] ?? $e;
                            // Punto del color del estado al que pasó (los mismos colores que las insignias de estado).
                            $colorEstado = [
                                'pendiente' => 'bg-amber-400', 'en_espera' => 'bg-sky-500', 'aprobada' => 'bg-indigo-500', 'aceptada' => 'bg-emerald-500',
                                'rechazada' => 'bg-red-500', 'no_concretada' => 'bg-red-500', 'cancelada' => 'bg-slate-400', 'cerrada' => 'bg-slate-400',
                            ];
                            $punto = match (true) {
                                $registro->accion === 'creado' => 'bg-amber-400',
                                array_key_exists('estado', $nuevos) => $colorEstado[$nuevos['estado']] ?? 'bg-blue-900',
                                default => 'bg-slate-300',
                            };
                        @endphp
                        <li class="relative">
                            <span class="absolute -start-[1.55rem] top-0.5 h-3.5 w-3.5 rounded-full ring-4 ring-white {{ $punto }}" aria-hidden="true"></span>
                            <p class="text-xs text-slate-500">{{ $registro->created_at?->format('d/m/Y H:i') }}</p>
                            <p class="font-semibold text-slate-900">
                                @if ($registro->accion === 'creado')
                                    Solicitud recibida
                                @elseif (array_key_exists('estado', $nuevos))
                                    {{ $legible($anteriores['estado'] ?? '') }} → {{ $legible($nuevos['estado']) }}
                                @else
                                    Nota interna (el estado no cambió)
                                @endif
                            </p>
                            <p class="text-slate-600">
                                {{ $registro->usuario ? $registro->usuario->nombres . ' ' . $registro->usuario->apellidos : 'Sistema' }}
                                @if (filled($nuevos['comentarios_admin'] ?? null)) · {{ in_array($nuevos['estado'] ?? null, ['rechazada'], true) ? 'Motivo' : 'Comentario' }}: {{ $nuevos['comentarios_admin'] }}@endif
                            </p>
                        </li>
                    @empty
                        <li class="text-slate-500">Sin registros.</li>
                    @endforelse
                </ol>
            </x-admin.tarjeta>
        </div>
    </div>
</x-admin.pagina>
