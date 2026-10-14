@use('App\Support\CuestionarioAdopcion', 'C')
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de solicitud de adopción</h2></x-slot>

    @php
        $alertas = collect($solicitud->alertas ?? []);
        $alertaPorPregunta = $alertas->groupBy('pregunta')->map(fn ($g) => $g->contains('nivel', 'rojo') ? 'rojo' : 'amarillo');
        $etapas = [
            'pendiente' => ['1 · Pendiente', 'Primera revisión del cuestionario.'],
            'en_espera' => ['2 · En evaluación', 'Entrevista o visita al hogar. La mascota sigue disponible.'],
            'final' => ['3 · Aceptada o rechazada', 'Decisión final después de la visita.'],
        ];
        $paso = match ($solicitud->estado) { 'pendiente' => 1, 'en_espera' => 2, default => 3 };
        $textarea = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $boton = 'inline-flex items-center rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest';
    @endphp

    <div class="py-12"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))<div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>@endif

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
                <p class="mt-3 text-xs text-gray-500">Las alertas no aprueban ni rechazan: ayudan a decidir qué conversar o verificar en la entrevista o la visita.</p>
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
                            <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2"><dt class="text-gray-500"><span class="font-semibold text-indigo-700">23–26.</span> Consentimientos obligatorios</dt><dd class="font-medium text-gray-900">Aceptados (visita previa, seguimiento, no ceder y declaración de veracidad)</dd></div>
                        @endif
                    </dl>
                @endforeach
            </section>

        {{-- 3. Decisión --}}
        <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="titulo-decision">
            <h3 id="titulo-decision" class="text-base font-semibold text-gray-900">Decisión del administrador</h3>
            <ol class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3" aria-label="Etapas de la solicitud">
                @foreach ($etapas as $clave => [$titulo, $texto])
                    @php
                        $i = $loop->iteration;
                        $clase = $i < $paso ? 'border-green-500 bg-green-50'
                            : ($i === $paso ? (in_array($solicitud->estado, ['rechazada', 'cancelada', 'cerrada']) ? 'border-red-400 bg-red-50' : 'border-indigo-600 ring-2 ring-indigo-600') : 'border-gray-200');
                        if ($i === 3 && $solicitud->estado === 'aceptada') { $clase = 'border-green-500 bg-green-50'; }
                    @endphp
                    <li class="rounded-lg border-2 p-3 text-xs text-gray-600 {{ $clase }}"><span class="block text-sm font-semibold text-gray-900">{{ $titulo }}</span>{{ $texto }}</li>
                @endforeach
            </ol>

            @if ($solicitud->comentarios_admin)
                <p class="mt-4 text-sm"><span class="font-medium text-gray-500">Último motivo registrado:</span> <span class="whitespace-pre-line text-gray-900">{{ $solicitud->comentarios_admin }}</span></p>
            @endif

            @canany(['evaluate', 'accept', 'reject', 'requestInformation'], $solicitud)
                <div class="mt-5 grid gap-4">
                    <p class="text-sm text-gray-600">Elige una acción. <strong>El motivo es obligatorio</strong> y queda en el historial con tu nombre y la fecha.</p>
                    <x-input-error :messages="$errors->get('comentarios_admin')" />

                    @can('evaluate', $solicitud)
                        <form method="POST" action="{{ route('admin.solicitudes-adopcion.evaluate', $solicitud) }}" class="rounded-lg border border-indigo-200 p-4">
                            @csrf @method('PATCH')
                            <label for="motivo-evaluar" class="text-sm font-semibold text-indigo-900">Continuar a entrevista o visita</label>
                            <p class="text-xs text-gray-500">La solicitud pasa a "En evaluación". La mascota sigue disponible hasta la decisión final.</p>
                            <textarea id="motivo-evaluar" name="comentarios_admin" rows="2" maxlength="1000" required class="{{ $textarea }}" placeholder="Ej. Cuestionario completo y sin condiciones incompatibles. Coordinar visita el sábado."></textarea>
                            <button type="submit" class="{{ $boton }} mt-2 bg-indigo-600 text-white hover:bg-indigo-700">Continuar a entrevista o visita</button>
                        </form>
                    @endcan

                    @can('accept', $solicitud)
                        <form method="POST" action="{{ route('admin.solicitudes-adopcion.accept', $solicitud) }}" class="rounded-lg border border-green-200 p-4"
                              x-data x-on:submit="if (! confirm({{ Js::from('¿Confirmas que deseas aceptar la adopción? ' . $solicitud->mascota->nombre . ' quedará adoptada.') }})) $event.preventDefault()">
                            @csrf @method('PATCH')
                            <label for="motivo-aceptar" class="text-sm font-semibold text-green-900">Aceptar adopción</label>
                            <p class="text-xs text-gray-500">{{ $solicitud->mascota->nombre }} pasará a "Adoptada", se cerrarán las demás solicitudes activas y se creará el seguimiento post-adopción.</p>
                            <textarea id="motivo-aceptar" name="comentarios_admin" rows="2" maxlength="1000" required class="{{ $textarea }}" placeholder="Ej. Visita realizada: patio cercado y toda la familia de acuerdo."></textarea>
                            <button type="submit" class="{{ $boton }} mt-2 bg-green-600 text-white hover:bg-green-700">Aceptar adopción</button>
                        </form>
                    @endcan

                    @can('requestInformation', $solicitud)
                        <form method="POST" action="{{ route('admin.solicitudes-adopcion.request-information', $solicitud) }}" class="rounded-lg border border-gray-200 p-4">
                            @csrf @method('PATCH')
                            <label for="motivo-info" class="text-sm font-semibold text-gray-900">Solicitar información</label>
                            <p class="text-xs text-gray-500">El estado no cambia. Registra qué falta confirmar con el solicitante.</p>
                            <textarea id="motivo-info" name="comentarios_admin" rows="2" maxlength="1000" required class="{{ $textarea }}" placeholder="Ej. Confirmar por escrito que el propietario permite mascotas."></textarea>
                            <button type="submit" class="{{ $boton }} mt-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Registrar solicitud de información</button>
                        </form>
                    @endcan

                    @can('reject', $solicitud)
                        <form method="POST" action="{{ route('admin.solicitudes-adopcion.reject', $solicitud) }}" class="rounded-lg border border-red-200 p-4"
                              x-data x-on:submit="if (! confirm({{ Js::from('¿Confirmas que deseas rechazar esta solicitud? No se podrá volver a procesar.') }})) $event.preventDefault()">
                            @csrf @method('PATCH')
                            <label for="motivo-rechazar" class="text-sm font-semibold text-red-900">Rechazar</label>
                            <p class="text-xs text-gray-500">La solicitud se cierra. La mascota sigue disponible para otras personas.</p>
                            <textarea id="motivo-rechazar" name="comentarios_admin" rows="2" maxlength="1000" required class="{{ $textarea }}" placeholder="Ej. El propietario confirmó que no permite mascotas."></textarea>
                            <button type="submit" class="{{ $boton }} mt-2 bg-red-600 text-white hover:bg-red-700">Rechazar</button>
                        </form>
                    @endcan
                </div>
            @else
                <p class="mt-4 text-sm text-gray-600">Estado final: {{ $solicitud->estadoLegible() }}. La solicitud ya no admite cambios.</p>
            @endcanany
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
                                Información solicitada (el estado no cambió)
                            @endif
                        </p>
                        <p class="text-gray-600">
                            {{ $registro->usuario ? $registro->usuario->nombres . ' ' . $registro->usuario->apellidos : 'Sistema' }}
                            @if (filled($nuevos['comentarios_admin'] ?? null)) · Motivo: {{ $nuevos['comentarios_admin'] }}@endif
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
