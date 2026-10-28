@use('App\Support\CuestionarioAdopcion', 'C')
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de solicitud de adopción</h2></x-slot>

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
        $textarea = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-900 focus:ring-blue-900 text-sm';
        $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-900 focus:ring-blue-900 text-sm';
        $boton = 'inline-flex items-center rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest transition';
        $mensajesFinales = [
            'rechazada' => $solicitud->comentarios_admin,
            'cancelada' => $solicitud->motivo_cancelacion,
            'cerrada' => 'La mascota fue adoptada mediante otra solicitud.',
            'no_concretada' => $solicitud->motivo_no_concretada,
        ];
    @endphp

    <div class="py-12"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <p class="font-semibold">Revisa la información enviada:</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Datos generales --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-gray-900">{{ $solicitud->mascota->nombre }} · {{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</h3>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-indigo-800">{{ $solicitud->estadoLegible() }}</span>
                    <x-nivel-alerta :nivel="$solicitud->nivel_alerta" />
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                <div><dt class="font-medium text-gray-500">Correo electrónico</dt><dd class="mt-1 text-gray-900">{{ $solicitud->solicitante->email }}</dd></div>
                <div><dt class="font-medium text-gray-500">Fecha de solicitud</dt><dd class="mt-1 text-gray-900">{{ $solicitud->created_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="font-medium text-gray-500">Zona aproximada</dt><dd class="mt-1 text-gray-900">{{ $solicitud->zona_aproximada }}</dd></div>
            </dl>
        </div>

        @can('contact', $solicitud)
            <section class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm" aria-label="Contacto con el solicitante">
                <h3 class="font-semibold text-blue-950">Contacto con el solicitante</h3>
                <p class="mt-1 mb-3 text-sm text-gray-600">La llamada o el mensaje se coordinan fuera del sistema; no hace falta registrar entrevista ni visita previa.</p>
                <x-botones-contacto
                    :telefono="$solicitud->solicitante->telefono"
                    :mensaje="$solicitud->estado"
                    :datos="['nombre' => \App\Support\ContactoTelefonico::primerNombre($solicitud->solicitante->nombres), 'mascota' => $solicitud->mascota->nombre]"
                />
            </section>
        @endcan

            {{-- 1. Alertas --}}
            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="titulo-alertas">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="titulo-alertas" class="text-base font-semibold text-gray-900">Alertas para el administrador</h3>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-red-800">{{ $alertas->where('nivel', 'rojo')->count() }} incompatibles</span>
                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-800">{{ $alertas->where('nivel', 'amarillo')->count() }} a revisar</span>
                    </div>
                </div>
                @if ($alertas->isEmpty())
                    <p class="mt-3 text-sm text-gray-600">Ninguna respuesta requiere atención especial.</p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($alertas as $alerta)
                            <li class="rounded-md px-3 py-2 text-sm {{ $alerta['nivel'] === 'rojo' ? 'bg-red-50 text-red-900' : 'bg-amber-50 text-amber-900' }}">
                                <span class="block text-xs font-bold uppercase tracking-wider">{{ $alerta['nivel'] === 'rojo' ? '🔴 Condición incompatible' : '🟡 Requiere revisión' }} · Pregunta {{ $alerta['pregunta'] }}</span>
                                {{ $alerta['mensaje'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-xs text-gray-500">Las alertas orientan la revisión del formulario; la decisión final corresponde a la administradora.</p>
            </section>

            {{-- 2. Respuestas --}}
            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="titulo-respuestas">
                <h3 id="titulo-respuestas" class="text-base font-semibold text-gray-900">Respuestas del cuestionario</h3>
                @foreach (C::SECCIONES as $seccion => $preguntas)
                    <h4 class="mt-5 text-xs font-bold uppercase tracking-wider text-gray-500">{{ $seccion }}</h4>
                    <dl class="mt-2 divide-y divide-gray-100 border-t border-gray-100 text-sm">
                        @foreach ($preguntas as [$num, $campo, $texto, $catalogo])
                            @php $valor = C::respuestaLegible($solicitud->respuestas, $campo, $catalogo); @endphp
                            @continue($valor === null)
                            <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2">
                                <dt class="text-gray-500"><span class="font-semibold text-indigo-700">{{ $num }}.</span> {{ $texto }}</dt>
                                <dd class="font-medium text-gray-900">
                                    @if ($nivel = $alertaPorPregunta[$num] ?? null)<span aria-hidden="true">{{ $nivel === 'rojo' ? '🔴' : '🟡' }}</span>@endif
                                    {{ $valor }}
                                </dd>
                            </div>

                        @endforeach
                        @if ($seccion === 'Visita')
                            <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2"><dt class="text-gray-500"><span class="font-semibold text-indigo-700">21.</span> Zona aproximada</dt><dd class="font-medium text-gray-900">{{ $solicitud->zona_aproximada }}</dd></div>
                            <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2"><dt class="text-gray-500"><span class="font-semibold text-indigo-700">23–26.</span> Consentimientos obligatorios</dt><dd class="font-medium text-gray-900">Aceptados ({{ in_array($solicitud->version_cuestionario, [null, 'a2-v2'], true) ? 'visita previa' : 'contacto por WhatsApp o llamada' }}, seguimiento, no ceder y declaración de veracidad)</dd></div>
                        @endif
                    </dl>
                @endforeach
            </section>

        {{-- 3. Flujo y acciones --}}
        <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="titulo-decision">
            <h3 id="titulo-decision" class="text-base font-semibold text-gray-900">Seguimiento de la solicitud</h3>
            <ol class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4" aria-label="Etapas de la solicitud">
                @foreach ($etapas as $i => [$titulo, $texto])
                    @php
                        $clase = $i < $paso ? 'border-green-500 bg-green-50'
                            : ($i === $paso ? ($etapaFinalSinAdopcion ? 'border-amber-500 bg-amber-50' : 'border-blue-900 bg-blue-50 ring-2 ring-blue-900') : 'border-gray-200');
                        if ($i === 4 && $solicitud->estado === 'aceptada') { $clase = 'border-green-500 bg-green-50'; }
                    @endphp
                    <li class="rounded-lg border-2 p-3 text-xs text-gray-600 {{ $clase }}"><span class="block text-sm font-semibold text-gray-900">{{ $i }} · {{ $titulo }}</span>{{ $texto }}</li>
                @endforeach
            </ol>

            @if (in_array($solicitud->estado, ['aprobada', 'aceptada'], true))
                <dl class="mt-5 grid grid-cols-1 gap-4 rounded-lg bg-blue-50 p-4 text-sm sm:grid-cols-2">
                    @if ($solicitud->numero_acta)<div><dt class="font-medium text-gray-500">Número de acta</dt><dd class="mt-1 font-semibold text-blue-950">{{ $solicitud->numero_acta }}</dd></div>@endif
                    @if ($solicitud->aprobada_at)<div><dt class="font-medium text-gray-500">Reservada el</dt><dd class="mt-1 text-gray-900">{{ $solicitud->aprobada_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</dd></div>@endif
                    @if ($solicitud->entregada_at)<div><dt class="font-medium text-gray-500">Entrega registrada</dt><dd class="mt-1 text-gray-900">{{ $solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</dd></div>@endif
                    @if ($solicitud->compromiso_firmado)<div><dt class="font-medium text-gray-500">Compromiso</dt><dd class="mt-1 text-gray-900">Firma confirmada al registrar la entrega</dd></div>@endif
                </dl>
            @endif

                @can('viewCommitment', $solicitud)
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                        <a href="{{ route('admin.solicitudes-adopcion.commitment-pdf', $solicitud) }}" class="font-semibold text-blue-900 underline">Descargar acta de adopción para firmar y sellar (PDF)</a>
                        @if ($solicitud->compromiso_foto_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($solicitud->compromiso_foto_path))
                            @can('viewCommitmentEvidence', $solicitud)<a href="{{ route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud) }}" class="font-semibold text-blue-900 underline">Descargar evidencia privada</a>@endcan
                        @endif
                    </div>
                    @if ($solicitud->estado === 'aprobada')
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud) }}" class="mt-4 rounded-lg border border-blue-100 p-4">
                            @csrf
                            <label for="evidencia-compromiso" class="block text-sm font-semibold text-blue-950">Adjuntar evidencia del compromiso firmado (JPG, PNG o PDF; máximo 10 MB)</label>
                            <input id="evidencia-compromiso" name="evidencia_compromiso" type="file" accept="image/jpeg,image/png,application/pdf" required class="mt-2 block w-full text-sm text-gray-700">
                            <x-input-error class="mt-1" :messages="$errors->get('evidencia_compromiso')" />
                            <p class="mt-2 text-xs text-gray-600">El archivo se almacena de forma privada. Adjuntarlo no confirma la firma; el formulario de entrega conserva su confirmación explícita.</p>
                            <button type="submit" class="{{ $boton }} mt-3 border border-blue-900 bg-white text-blue-950 hover:bg-blue-50">Guardar evidencia privada</button>
                        </form>
                    @endif
                @endcan

            @if (! in_array($solicitud->estado, ['aprobada', 'aceptada'], true) && $solicitud->compromiso_foto_path)
                @can('viewCommitmentEvidence', $solicitud)
                    <p class="mt-3 text-sm"><a href="{{ route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud) }}" class="font-semibold text-blue-900 underline">Descargar evidencia privada del compromiso</a></p>
                @endcan
            @endif

            @if (in_array($solicitud->estado, ['rechazada', 'cancelada', 'cerrada', 'no_concretada'], true))
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <p class="font-semibold">{{ $solicitud->estadoLegible() }}</p>
                    @if (filled($mensajesFinales[$solicitud->estado] ?? null))<p class="mt-1 whitespace-pre-line">{{ $mensajesFinales[$solicitud->estado] }}</p>@endif
                    @if ($solicitud->resuelta_at)<p class="mt-2 text-xs text-amber-800">Registrada el {{ $solicitud->resuelta_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>@endif
                </div>
            @endif

            @if ($solicitud->comentarios_admin && $solicitud->estado !== 'rechazada')
                <p class="mt-4 text-sm"><span class="font-medium text-gray-500">Último comentario interno:</span> <span class="whitespace-pre-line text-gray-900">{{ $solicitud->comentarios_admin }}</span></p>
            @endif

            @if ($solicitud->estado === 'pendiente')
                @can('evaluate', $solicitud)
                    <form method="POST" action="{{ route('admin.solicitudes-adopcion.evaluate', $solicitud) }}" class="mt-5 rounded-lg border border-blue-200 p-4">
                        @csrf @method('PATCH')
                        <label for="comentarios-evaluar" class="text-sm font-semibold text-blue-950">Pasar a evaluación</label>
                        <p class="mt-1 text-xs text-gray-500">El comentario es opcional. La mascota seguirá disponible durante la evaluación.</p>
                        <textarea id="comentarios-evaluar" name="comentarios_admin" rows="2" maxlength="1000" class="{{ $textarea }}" placeholder="Comentario interno opcional">{{ old('comentarios_admin') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                        <button type="submit" class="{{ $boton }} mt-3 bg-blue-950 text-white hover:bg-blue-900">Pasar a evaluación</button>
                    </form>
                @endcan
            @endif

            @if (in_array($solicitud->estado, ['pendiente', 'en_espera'], true))
                @can('reject', $solicitud)
                    <form method="POST" action="{{ route('admin.solicitudes-adopcion.reject', $solicitud) }}" class="mt-4 rounded-lg border border-red-200 p-4"
                          x-data x-on:submit="if (! confirm({{ Js::from('¿Confirmas que deseas rechazar esta solicitud? Esta decisión no se puede revertir.') }})) $event.preventDefault()">
                        @csrf @method('PATCH')
                        <label for="comentarios-rechazar" class="text-sm font-semibold text-red-900">Rechazar solicitud</label>
                        <textarea id="comentarios-rechazar" name="comentarios_admin" rows="2" minlength="5" maxlength="1000" required class="{{ $textarea }}" placeholder="Escribe el motivo (al menos 5 caracteres).">{{ old('comentarios_admin') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                        <button type="submit" class="{{ $boton }} mt-3 bg-red-700 text-white hover:bg-red-800">Rechazar</button>
                    </form>
                @endcan
            @endif

            @if ($solicitud->estado === 'en_espera')
                @if ($solicitud->mascota->estado !== 'disponible')
                    <p class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900">La mascota ya no está disponible para aprobación.</p>
                @endif
                @can('approve', $solicitud)
                    <form method="POST" action="{{ route('admin.solicitudes-adopcion.approve', $solicitud) }}" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4"
                          x-data x-on:submit="if (! confirm({{ Js::from('¿Aprobar la solicitud y reservar ' . $solicitud->mascota->nombre . '? La adopción se concretará después de registrar la entrega y confirmar la firma del compromiso.') }})) $event.preventDefault()">
                        @csrf @method('PATCH')
                        <h4 class="text-sm font-semibold text-green-950">Aprobar y reservar</h4>
                        <p class="mt-1 text-xs text-gray-600">Cuando ya hablaste con el solicitante por teléfono o WhatsApp, aprueba para reservar la mascota. La fecha se registra recién en la entrega.</p>
                        <label for="comentarios-aprobar" class="mt-3 block text-xs font-medium text-gray-700">Comentario para el equipo (opcional)</label>
                        <textarea id="comentarios-aprobar" name="comentarios_admin" rows="2" maxlength="1000" class="{{ $textarea }}" placeholder="Ej. Hablé por WhatsApp: vive en casa con patio y toda la familia está de acuerdo."></textarea>
                        <p class="mt-1 text-xs text-gray-500">Queda en el historial con tu nombre y la fecha, para que los demás administradores sepan por qué se aprobó.</p>
                        <button type="submit" class="{{ $boton }} mt-3 bg-green-700 text-white hover:bg-green-800">Aprobar solicitud</button>
                    </form>
                @endcan
            @endif

            @if ($solicitud->estado === 'aprobada')
                @can('deliver', $solicitud)
                    <form method="POST" action="{{ route('admin.solicitudes-adopcion.deliver', $solicitud) }}" class="mt-4 rounded-lg border border-green-200 p-4"
                          x-data x-on:submit="if (! confirm({{ Js::from('¿Confirmas que la entrega se concretó? Se marcará la mascota como adoptada y se creará el seguimiento.') }})) $event.preventDefault()">
                        @csrf @method('PATCH')
                        <h4 class="text-sm font-semibold text-green-950">Registrar entrega y concretar adopción</h4>
                        <label for="fecha-entrega-real" class="mt-3 block text-xs font-medium text-gray-700">Fecha real de entrega</label>
                        <input id="fecha-entrega-real" name="fecha_entrega" type="date" min="{{ $solicitud->aprobada_at?->timezone('America/La_Paz')->toDateString() }}" max="{{ now('America/La_Paz')->toDateString() }}" required value="{{ old('fecha_entrega') }}" class="{{ $input }}">
                        <x-input-error class="mt-1" :messages="$errors->get('fecha_entrega')" />
                        <label class="mt-3 flex items-start gap-2 text-sm text-gray-700"><input type="checkbox" name="compromiso_firmado" value="1" required @checked(old('compromiso_firmado')) class="mt-1 rounded border-gray-300 text-blue-900 focus:ring-blue-900"><span>Confirmo que el adoptante firmó el compromiso de adopción.</span></label>
                        <x-input-error class="mt-1" :messages="$errors->get('compromiso_firmado')" />
                        <label for="comentarios-entrega" class="mt-3 block text-xs font-medium text-gray-700">Comentario interno (opcional)</label>
                        <textarea id="comentarios-entrega" name="comentarios_admin" rows="2" maxlength="1000" class="{{ $textarea }}">{{ old('comentarios_admin') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('comentarios_admin')" />
                        <button type="submit" class="{{ $boton }} mt-3 bg-green-700 text-white hover:bg-green-800">Registrar entrega y finalizar adopción</button>
                    </form>
                @endcan
                @can('markNotDelivered', $solicitud)
                    <form method="POST" action="{{ route('admin.solicitudes-adopcion.not-delivered', $solicitud) }}" class="mt-4 rounded-lg border border-amber-200 p-4"
                          x-data x-on:submit="if (! confirm({{ Js::from('¿Marcar la entrega como no concretada? ' . $solicitud->mascota->nombre . ' volverá a estar disponible.') }})) $event.preventDefault()">
                        @csrf @method('PATCH')
                        <label for="motivo-no-concretada" class="text-sm font-semibold text-amber-950">Marcar entrega no concretada</label>
                        <textarea id="motivo-no-concretada" name="motivo_no_concretada" rows="2" minlength="5" maxlength="1000" required class="{{ $textarea }}" placeholder="Indica el motivo (al menos 5 caracteres).">{{ old('motivo_no_concretada') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('motivo_no_concretada')" />
                        <button type="submit" class="{{ $boton }} mt-3 border border-amber-600 bg-white text-amber-950 hover:bg-amber-50">Registrar entrega no concretada</button>
                    </form>
                @endcan
            @endif

            @if ($solicitud->estado === 'aceptada')
                <div class="mt-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-950"><p class="font-semibold">Adopción concretada</p><p class="mt-1">La entrega quedó registrada y la mascota fue marcada como adoptada.</p>
                    @if ($solicitud->seguimiento)<a href="{{ route('admin.seguimientos.show', $solicitud->seguimiento) }}" class="mt-3 inline-flex font-semibold text-blue-900 underline hover:text-blue-700">Abrir seguimiento post-adopción</a>@else<p class="mt-2 text-amber-800">Todavía no hay un seguimiento asociado.</p>@endif
                </div>
            @endif

            @if ($solicitud->estado === 'rechazada')<p class="mt-4 text-sm text-gray-600">Esta solicitud fue rechazada y ya no admite cambios.</p>@elseif ($solicitud->estado === 'cancelada')<p class="mt-4 text-sm text-gray-600">Esta solicitud fue cancelada y ya no admite cambios.</p>@elseif ($solicitud->estado === 'cerrada')<p class="mt-4 text-sm text-gray-600">Esta solicitud se cerró porque la mascota fue adoptada mediante otra solicitud.</p>@elseif ($solicitud->estado === 'no_concretada')<p class="mt-4 text-sm text-gray-600">La entrega no se concretó; la mascota volvió a estar disponible.</p>@endif
        </section>
        {{-- 4. Historial --}}
        <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="titulo-historial">
            <h3 id="titulo-historial" class="text-base font-semibold text-gray-900">Historial de la solicitud</h3>
            <ol class="mt-3 space-y-3 border-l-2 border-gray-200 pl-4 text-sm">
                @forelse ($historial as $registro)
                    @php
                        $nuevos = $registro->valores_nuevos ?? [];
                        $anteriores = $registro->valores_anteriores ?? [];
                        $legible = fn ($e) => \App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES[$e] ?? $e;
                    @endphp
                    <li>
                        <p class="font-semibold text-gray-900">
                            {{ $registro->created_at?->format('d/m/Y H:i') }} ·
                            @if ($registro->accion === 'creado')
                                Solicitud recibida
                            @elseif (array_key_exists('estado', $nuevos))
                                {{ $legible($anteriores['estado'] ?? '') }} → {{ $legible($nuevos['estado']) }}
                            @else
                                Nota interna (el estado no cambió)
                            @endif
                        </p>
                        <p class="text-gray-600">
                            {{ $registro->usuario ? $registro->usuario->nombres . ' ' . $registro->usuario->apellidos : 'Sistema' }}
                            @if (filled($nuevos['comentarios_admin'] ?? null)) · {{ in_array($nuevos['estado'] ?? null, ['rechazada'], true) ? 'Motivo' : 'Comentario' }}: {{ $nuevos['comentarios_admin'] }}@endif
                        </p>
                    </li>
                @empty
                    <li class="text-gray-500">Sin registros.</li>
                @endforelse
            </ol>
        </section>

        <a href="{{ route('admin.solicitudes-adopcion.index') }}" class="inline-block text-sm text-gray-600 hover:text-gray-900">Volver al listado</a>
    </div></div>
</x-app-layout>
