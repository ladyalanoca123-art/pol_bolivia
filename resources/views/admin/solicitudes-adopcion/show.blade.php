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
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="text-sm text-slate-600">Quiere adoptar a <a href="{{ route('admin.mascotas.show', $solicitud->mascota) }}" class="font-semibold text-blue-900 hover:underline">{{ $solicitud->mascota->nombre }}</a></span>
            <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
            <x-nivel-alerta :nivel="$solicitud->nivel_alerta" />
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Revisa la información enviada:</p>
            <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Etapas --}}
    <ol class="grid grid-cols-2 gap-2 lg:grid-cols-4" aria-label="Etapas de la solicitud">
        @foreach ($etapas as $i => [$titulo, $texto])
            @php
                $clase = $i < $paso ? 'border-emerald-300 bg-emerald-50'
                    : ($i === $paso ? ($etapaFinalSinAdopcion ? 'border-amber-400 bg-amber-50' : 'border-blue-900 bg-white ring-2 ring-blue-900') : 'border-slate-200 bg-white');
                if ($i === 4 && $solicitud->estado === 'aceptada') { $clase = 'border-emerald-300 bg-emerald-50'; }
            @endphp
            <li class="rounded-xl border p-3 text-xs text-slate-600 {{ $clase }}"><span class="block text-sm font-semibold text-blue-950">{{ $i }} · {{ $titulo }}</span>{{ $texto }}</li>
        @endforeach
    </ol>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Columna principal --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">

            {{-- Acciones de la etapa actual --}}
            <x-admin.tarjeta titulo="Seguimiento de la solicitud">
                @if (in_array($solicitud->estado, ['rechazada', 'cancelada', 'cerrada', 'no_concretada'], true))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <p class="font-semibold">{{ $solicitud->estadoLegible() }}</p>
                        @if (filled($mensajesFinales[$solicitud->estado] ?? null))<p class="mt-1 whitespace-pre-line">{{ $mensajesFinales[$solicitud->estado] }}</p>@endif
                        @if ($solicitud->resuelta_at)<p class="mt-2 text-xs text-amber-800">Registrada el {{ $solicitud->resuelta_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>@endif
                    </div>
                @endif

                @if ($solicitud->comentarios_admin && $solicitud->estado !== 'rechazada')
                    <p class="mb-4 rounded-xl bg-slate-50 p-3 text-sm"><span class="font-semibold text-slate-500">Último comentario interno:</span> <span class="whitespace-pre-line text-slate-900">{{ $solicitud->comentarios_admin }}</span></p>
                @endif

                <div class="space-y-4">
                    @if ($solicitud->estado === 'pendiente')
                        @can('evaluate', $solicitud)
                            <form method="POST" action="{{ route('admin.solicitudes-adopcion.evaluate', $solicitud) }}" class="{{ $accion }} border-blue-200 bg-blue-50/50">
                                @csrf @method('PATCH')
                                <label for="comentarios-evaluar" class="text-sm font-semibold text-blue-950">Pasar a evaluación</label>
                                <p class="admin-ayuda">El comentario es opcional. La mascota seguirá disponible durante la evaluación.</p>
                                <textarea id="comentarios-evaluar" name="comentarios_admin" rows="2" maxlength="1000" class="admin-campo" placeholder="Comentario interno opcional">{{ old('comentarios_admin') }}</textarea>
                                <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
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
                                <label for="comentarios-aprobar" class="admin-etiqueta mt-3">Comentario para el equipo (opcional)</label>
                                <textarea id="comentarios-aprobar" name="comentarios_admin" rows="2" maxlength="1000" class="admin-campo" placeholder="Ej. Hablé por WhatsApp: vive en casa con patio y toda la familia está de acuerdo."></textarea>
                                <p class="admin-ayuda">Queda en el historial con tu nombre y la fecha, para que los demás administradores sepan por qué se aprobó.</p>
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
                                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="fecha-entrega-real" class="admin-etiqueta">Fecha real de entrega</label>
                                        <input id="fecha-entrega-real" name="fecha_entrega" type="date" min="{{ $solicitud->aprobada_at?->timezone('America/La_Paz')->toDateString() }}" max="{{ now('America/La_Paz')->toDateString() }}" required value="{{ old('fecha_entrega') }}" class="admin-campo">
                                        <x-input-error class="mt-1" :messages="$errors->get('fecha_entrega')" />
                                    </div>
                                    <div>
                                        <label for="comentarios-entrega" class="admin-etiqueta">Comentario interno (opcional)</label>
                                        <textarea id="comentarios-entrega" name="comentarios_admin" rows="1" maxlength="1000" class="admin-campo">{{ old('comentarios_admin') }}</textarea>
                                        <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                                    </div>
                                </div>
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
            <x-admin.tarjeta titulo="Alertas para el administrador">
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
            <x-admin.tarjeta titulo="Respuestas del cuestionario">
                <div class="space-y-5">
                    @foreach (C::SECCIONES as $seccion => $preguntas)
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $seccion }}</h3>
                            <dl class="mt-2 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                                @foreach ($preguntas as [$num, $campo, $texto, $catalogo])
                                    @php $valor = C::respuestaLegible($solicitud->respuestas, $campo, $catalogo); @endphp
                                    @continue($valor === null)
                                    @php $nivel = $alertaPorPregunta[$num] ?? null; @endphp
                                    <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2 {{ $nivel === 'rojo' ? 'bg-red-50/60' : ($nivel ? 'bg-amber-50/60' : '') }}">
                                        <dt class="text-slate-500"><span class="font-semibold text-blue-800">{{ $num }}.</span> {{ $texto }}</dt>
                                        <dd class="font-medium text-slate-900">
                                            @if ($nivel)<span class="me-1 inline-block h-2 w-2 rounded-full {{ $nivel === 'rojo' ? 'bg-red-500' : 'bg-amber-400' }}" aria-hidden="true"></span>@endif
                                            {{ $valor }}
                                        </dd>
                                    </div>
                                @endforeach
                                @if ($seccion === 'Visita')
                                    <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2"><dt class="text-slate-500"><span class="font-semibold text-blue-800">21.</span> Zona aproximada</dt><dd class="font-medium text-slate-900">{{ $solicitud->zona_aproximada }}</dd></div>
                                    <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2"><dt class="text-slate-500"><span class="font-semibold text-blue-800">23–26.</span> Consentimientos obligatorios</dt><dd class="font-medium text-slate-900">Aceptados ({{ in_array($solicitud->version_cuestionario, [null, 'a2-v2'], true) ? 'visita previa' : 'contacto por WhatsApp o llamada' }}, seguimiento, no ceder y declaración de veracidad)</dd></div>
                                @endif
                            </dl>
                        </div>
                    @endforeach
                </div>
            </x-admin.tarjeta>
        </div>

        {{-- Columna lateral: en celular va primero (contacto y datos del solicitante a la mano). --}}
        <div class="order-first min-w-0 space-y-6 lg:order-none">
            <x-admin.tarjeta titulo="Solicitante">
                <dl class="space-y-3">
                    <x-admin.dato etiqueta="Correo electrónico">{{ $solicitud->solicitante->email }}</x-admin.dato>
                    <x-admin.dato etiqueta="Teléfono">{{ $solicitud->solicitante->telefono ?: '—' }}</x-admin.dato>
                    <x-admin.dato etiqueta="Zona aproximada">{{ $solicitud->zona_aproximada }}</x-admin.dato>
                    <x-admin.dato etiqueta="Fecha de solicitud">{{ $solicitud->created_at->format('d/m/Y H:i') }}</x-admin.dato>
                </dl>
                @can('contact', $solicitud)
                    <div class="mt-4 border-t border-slate-100 pt-4" aria-label="Contacto con el solicitante">
                        <p class="mb-3 text-xs text-slate-500">La llamada o el mensaje se coordinan fuera del sistema; no hace falta registrar entrevista ni visita previa.</p>
                        <x-botones-contacto
                            :telefono="$solicitud->solicitante->telefono"
                            :mensaje="$solicitud->estado"
                            :datos="['nombre' => \App\Support\ContactoTelefonico::primerNombre($solicitud->solicitante->nombres), 'mascota' => $solicitud->mascota->nombre]"
                        />
                    </div>
                @endcan
            </x-admin.tarjeta>

            @if (in_array($solicitud->estado, ['aprobada', 'aceptada'], true) || $solicitud->compromiso_foto_path)
                <x-admin.tarjeta titulo="Acta de adopción">
                    @if (in_array($solicitud->estado, ['aprobada', 'aceptada'], true))
                        <dl class="space-y-3">
                            @if ($solicitud->numero_acta)<x-admin.dato etiqueta="Número de acta"><span class="font-semibold text-blue-950">{{ $solicitud->numero_acta }}</span></x-admin.dato>@endif
                            @if ($solicitud->aprobada_at)<x-admin.dato etiqueta="Reservada el">{{ $solicitud->aprobada_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</x-admin.dato>@endif
                            @if ($solicitud->entregada_at)<x-admin.dato etiqueta="Entrega registrada">{{ $solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</x-admin.dato>@endif
                            @if ($solicitud->compromiso_firmado)<x-admin.dato etiqueta="Compromiso">Firma confirmada al registrar la entrega</x-admin.dato>@endif
                        </dl>
                    @endif

                    @can('viewCommitment', $solicitud)
                        <div class="mt-4 flex flex-col gap-2">
                            <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.solicitudes-adopcion.commitment-pdf', $solicitud)">Descargar acta de adopción para firmar y sellar (PDF)</x-admin.boton>
                            @if ($solicitud->compromiso_foto_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($solicitud->compromiso_foto_path))
                                @can('viewCommitmentEvidence', $solicitud)<x-admin.boton variante="secundario" tamano="chico" :href="route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud)">Descargar evidencia privada</x-admin.boton>@endcan
                            @endif
                        </div>
                        @can('uploadCommitmentEvidence', $solicitud)
                            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud) }}" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
                                @csrf
                                <label for="evidencia-compromiso" class="block text-sm font-semibold text-blue-950">{{ $solicitud->compromiso_foto_path ? 'Reemplazar la foto del acta firmada' : 'Subir la foto del acta firmada' }} (JPG, PNG o PDF; máximo 10 MB)</label>
                                <input id="evidencia-compromiso" name="evidencia_compromiso" type="file" accept="image/jpeg,image/png,application/pdf" required class="admin-archivo mt-2">
                                <x-input-error class="mt-1" :messages="$errors->get('evidencia_compromiso')" />
                                <p class="admin-ayuda">Se guarda de forma privada. Puedes subirla antes o después de registrar la entrega. Subirla no reemplaza la casilla «El adoptante firmó el compromiso».</p>
                                <x-admin.boton variante="secundario" tamano="chico" class="mt-3">Guardar foto del acta</x-admin.boton>
                            </form>
                        @endcan
                    @endcan

                    @if (! in_array($solicitud->estado, ['aprobada', 'aceptada'], true) && $solicitud->compromiso_foto_path)
                        @can('viewCommitmentEvidence', $solicitud)
                            <p class="text-sm"><a href="{{ route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud) }}" class="font-semibold text-blue-900 underline">Descargar evidencia privada del compromiso</a></p>
                        @endcan
                    @endif
                </x-admin.tarjeta>
            @endif

            <x-admin.tarjeta titulo="Historial de la solicitud">
                <ol class="space-y-4 border-l-2 border-slate-200 ps-4 text-sm">
                    @forelse ($historial as $registro)
                        @php
                            $nuevos = $registro->valores_nuevos ?? [];
                            $anteriores = $registro->valores_anteriores ?? [];
                            $legible = fn ($e) => \App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES[$e] ?? $e;
                        @endphp
                        <li class="relative">
                            <span class="absolute -start-[1.4rem] top-1 h-2.5 w-2.5 rounded-full border-2 border-white bg-blue-900" aria-hidden="true"></span>
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
