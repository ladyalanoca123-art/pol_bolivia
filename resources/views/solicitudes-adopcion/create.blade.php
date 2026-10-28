@use('App\Support\CuestionarioAdopcion', 'C')
<x-app-layout>
    @php
        $sec = 'rounded-lg border border-gray-200 bg-white p-6 shadow-sm';
        $input = 'mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-700 focus:ring-blue-700';
        $fotos = $mascota->fotos->where('tipo', 'foto');
        $fotoPrincipal = $fotos->firstWhere('es_principal', true) ?? $fotos->first();
    @endphp

    {{-- Indicador de progreso: cuenta preguntas numeradas (la 11 es una sola aunque tenga 4 campos). --}}
    <x-cuestionario.progreso />

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav aria-label="Ruta de navegación" class="mb-4 text-sm">
                <ol class="flex flex-wrap items-center gap-1 text-gray-600">
                    <li><a href="{{ route('mascotas.catalogo') }}" class="font-medium text-blue-800 hover:text-blue-950 hover:underline">Mascotas</a></li>
                    <li aria-hidden="true" class="text-gray-400">›</li>
                    <li><a href="{{ route('mascotas.public.show', $mascota) }}" class="font-medium text-blue-800 hover:text-blue-950 hover:underline">{{ $mascota->nombre }}</a></li>
                    <li aria-hidden="true" class="text-gray-400">›</li>
                    <li aria-current="page" class="font-semibold text-blue-950">Solicitud</li>
                </ol>
            </nav>

            <div class="mb-6 flex flex-col gap-4 rounded-lg bg-blue-950 p-6 text-white sm:flex-row sm:items-center">
                @if ($fotoPrincipal)
                    <img src="{{ $fotoPrincipal->url_miniatura }}" alt="Foto de {{ $mascota->nombre }}" class="h-16 w-16 shrink-0 rounded-xl object-cover ring-2 ring-amber-400 sm:h-20 sm:w-20">
                @else
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-blue-900 text-amber-300 ring-2 ring-amber-400 sm:h-20 sm:w-20" role="img" aria-label="{{ $mascota->nombre }} todavía no tiene foto">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg>
                    </div>
                @endif
                <div class="min-w-0">
                <h3 class="text-2xl font-bold">Solicitud para {{ $mascota->nombre }}</h3>
                <p class="mt-1 text-sm text-sky-100">Este cuestionario es una primera evaluación. Ninguna respuesta aprueba o rechaza tu solicitud por sí sola: la fundación revisará tu información y te contactará por WhatsApp o llamada.</p>
                </div>
            </div>

            @if ($errors->has('mascota'))
                <p class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('mascota') }}</p>
            @elseif ($errors->any())
                <p class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">Revisa las preguntas marcadas en rojo antes de enviar.</p>
            @endif

            <form method="POST" action="{{ route('solicitudes-adopcion.store', $mascota) }}" class="space-y-6"
                  x-data="cuestionarioFormulario({
                      tenencia: @js(old('tenencia')),
                      permanencia: @js(old('permanencia')),
                      ninos: @js((string) old('ninos', '')),
                      otras: @js(old('otras_mascotas')),
                      anteriores: @js(old('mascotas_anteriores')),
                      responsable: @js(old('responsable')),
                      motivo: @js(old('motivo')),
                      sinopuede: @js(old('si_no_puede')),
                      conExterior: @js(C::PERMANENCIA_CON_EXTERIOR),
                      conDetalle: @js(C::ANTERIORES_CON_DETALLE),
                  })"
                  x-on:input="$nextTick(() => progreso())" x-on:change="$nextTick(() => progreso())">
                @csrf

                <div class="sticky top-0 z-10 -mx-1 rounded-md bg-gray-100/95 px-3 py-2 text-sm text-gray-700 shadow-sm" aria-live="polite">
                    <div class="flex justify-between gap-3"><span x-text="texto"></span><span x-text="porcentaje + ' %'"></span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-300"><div class="h-full bg-amber-400 transition-all" :style="`width: ${porcentaje}%`"></div></div>
                </div>

                {{-- Sección 1 · Vivienda --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Vivienda</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 1 de 6</p>
                    <h4 class="text-lg font-semibold text-gray-900">Vivienda</h4>
                    <p class="text-sm text-gray-600">Nos ayuda a conocer el lugar donde vivirá la mascota.</p>

                    <x-cuestionario.pregunta tema="pol" num="1" label="¿Qué tipo de vivienda tiene?" campo="tipo_vivienda">
                        <x-cuestionario.opciones tema="pol" name="tipo_vivienda" catalogo="tipo_vivienda" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="2" label="La vivienda es:" campo="tenencia">
                        <x-cuestionario.opciones tema="pol" name="tenencia" catalogo="tenencia" model="tenencia" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="3" label="¿El propietario o responsable de la vivienda permite tener mascotas?" campo="permiso_propietario" ayuda="Aparece porque la vivienda no es propia." condicional x-show="tenencia && tenencia !== 'propia'" x-cloak>
                        <x-cuestionario.opciones tema="pol" name="permiso_propietario" catalogo="permiso_propietario" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="4" label="¿Dónde permanecerá principalmente la mascota?" campo="permanencia">
                        <x-cuestionario.opciones tema="pol" name="permanencia" catalogo="permanencia" model="permanencia" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="5" label="Si dispone de un espacio exterior, ¿cuenta con un cerramiento seguro para evitar escapes?" campo="cerramiento" ayuda="Aparece porque la mascota usará un espacio exterior." condicional x-show="conExterior.includes(permanencia)" x-cloak>
                        <x-cuestionario.opciones tema="pol" name="cerramiento" catalogo="cerramiento" />
                    </x-cuestionario.pregunta>
                </fieldset>

                {{-- Sección 2 · Hogar --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Hogar</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 2 de 6</p>
                    <h4 class="text-lg font-semibold text-gray-900">Hogar</h4>
                    <p class="text-sm text-gray-600">Una adopción funciona cuando la aceptan todos los que viven en la casa.</p>

                    <x-cuestionario.pregunta tema="pol" num="6" label="¿Cuántos adultos viven habitualmente en el hogar, incluyéndose?" campo="adultos">
                        <input type="number" name="adultos" id="adultos" min="1" max="20" inputmode="numeric" value="{{ old('adultos') }}" class="{{ $input }} max-w-[8rem]" placeholder="Ej. 2">
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="7" label="¿Cuántos niños viven habitualmente en el hogar?" campo="ninos" ayuda="Puede indicar 0.">
                        <input type="number" name="ninos" id="ninos" min="0" max="20" inputmode="numeric" x-model="ninos" class="{{ $input }} max-w-[8rem]" placeholder="Ej. 0">
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="8" label="¿Qué edades tienen los niños?" campo="edades_ninos" ayuda="Aparece porque hay niños en el hogar." condicional x-show="Number(ninos) > 0" x-cloak>
                        <input type="text" name="edades_ninos" id="edades_ninos" maxlength="100" value="{{ old('edades_ninos') }}" class="{{ $input }}" placeholder="Ej. 4 y 9 años" autocomplete="off">
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="9" label="¿Todos los miembros del hogar están de acuerdo con la adopción?" campo="acuerdo_hogar">
                        <x-cuestionario.opciones tema="pol" name="acuerdo_hogar" catalogo="acuerdo_hogar" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="10" label="¿Tiene otras mascotas actualmente?" campo="otras_mascotas">
                        <x-cuestionario.opciones tema="pol" name="otras_mascotas" catalogo="otras_mascotas" model="otras" />
                    </x-cuestionario.pregunta>

                    <div x-show="otras === 'si'" x-cloak class="rounded-lg border-l-4 border-amber-300 bg-gray-50 px-4 py-2">
                        <p class="pt-2 text-sm font-semibold text-blue-800"><span class="text-blue-800">11.</span> Información sobre sus mascotas actuales</p>
                        <p class="text-xs text-gray-500">Aparece porque tiene otras mascotas.</p>
                        <x-cuestionario.pregunta tema="pol" num="11" label="¿Cuántas mascotas tiene?" campo="mascotas_actuales.cantidad" class="!border-t-0">
                            <input type="number" name="mascotas_actuales[cantidad]" id="mascotas_actuales-cantidad" min="1" max="30" inputmode="numeric" value="{{ old('mascotas_actuales.cantidad') }}" class="{{ $input }} max-w-[8rem]" placeholder="Ej. 1">
                        </x-cuestionario.pregunta>
                        <x-cuestionario.pregunta tema="pol" num="11" label="¿Qué especies tiene? (puede marcar varias)" campo="mascotas_actuales.especies">
                            <x-cuestionario.opciones tema="pol" name="mascotas_actuales[especies]" catalogo="especies" multiple />
                        </x-cuestionario.pregunta>
                        <x-cuestionario.pregunta tema="pol" num="11" label="¿Sus vacunas están al día?" campo="mascotas_actuales.vacunas">
                            <x-cuestionario.opciones tema="pol" name="mascotas_actuales[vacunas]" catalogo="estado_grupo" />
                        </x-cuestionario.pregunta>
                        <x-cuestionario.pregunta tema="pol" num="11" label="¿Están esterilizadas?" campo="mascotas_actuales.esterilizadas">
                            <x-cuestionario.opciones tema="pol" name="mascotas_actuales[esterilizadas]" catalogo="estado_grupo" />
                        </x-cuestionario.pregunta>
                    </div>
                </fieldset>

                {{-- Sección 3 · Experiencia --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Experiencia</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 3 de 6</p>
                    <h4 class="text-lg font-semibold text-gray-900">Experiencia</h4>
                    <p class="text-sm text-gray-600">Qué pasó con las mascotas que tuvo antes.</p>

                    <x-cuestionario.pregunta tema="pol" num="12" label="¿Qué ocurrió con las mascotas que tuvo anteriormente?" campo="mascotas_anteriores">
                        <x-cuestionario.opciones tema="pol" name="mascotas_anteriores" catalogo="mascotas_anteriores" model="anteriores" columna />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="13" label="Cuéntenos brevemente qué ocurrió." campo="que_ocurrio" condicional
                        nota="La respuesta sincera no implica el rechazo de la solicitud. El administrador evaluará el contexto."
                        x-show="conDetalle.includes(anteriores)" x-cloak>
                        <textarea name="que_ocurrio" id="que_ocurrio" rows="3" maxlength="500" class="{{ $input }}" placeholder="Ej. Me mudé a un lugar donde no permitían perros y quedó al cuidado de mi hermana.">{{ old('que_ocurrio') }}</textarea>
                    </x-cuestionario.pregunta>
                </fieldset>

                {{-- Sección 4 · Rutina y cuidados --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Rutina y cuidados</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 4 de 6</p>
                    <h4 class="text-lg font-semibold text-gray-900">Rutina y cuidados</h4>
                    <p class="text-sm text-gray-600">Quién cuidará a la mascota, si hay apoyo y cuánto tiempo estará sola.</p>

                    <x-cuestionario.pregunta tema="pol" num="14" label="¿Quién será el responsable principal de la mascota?" campo="responsable">
                        <x-cuestionario.opciones tema="pol" name="responsable" catalogo="responsable" model="responsable" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="15" label="Indique quién será." campo="responsable_otro" ayuda='Aparece porque seleccionó "Otra persona".' condicional x-show="responsable === 'otra_persona'" x-cloak>
                        <input type="text" name="responsable_otro" id="responsable_otro" maxlength="100" value="{{ old('responsable_otro') }}" class="{{ $input }}" placeholder="Ej. Mi madre, que vive al lado" autocomplete="off">
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="16" label="Cuando usted no esté, ¿habrá otra persona que pueda atender a la mascota si fuera necesario?" campo="apoyo">
                        <x-cuestionario.opciones tema="pol" name="apoyo" catalogo="apoyo" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="17" label="¿Cuánto tiempo permanecería sola la mascota en un día habitual?" campo="tiempo_sola">
                        <x-cuestionario.opciones tema="pol" name="tiempo_sola" catalogo="tiempo_sola" />
                    </x-cuestionario.pregunta>
                </fieldset>

                {{-- Sección 5 · Compromiso --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Compromiso</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 5 de 6</p>
                    <h4 class="text-lg font-semibold text-gray-900">Compromiso</h4>
                    <p class="text-sm text-gray-600">Motivo de la adopción, vacunas y qué haría si ya no pudiera cuidarla.</p>

                    <x-cuestionario.pregunta tema="pol" num="18" label="¿Cuál es el principal motivo por el que desea adoptar?" campo="motivo">
                        <x-cuestionario.opciones tema="pol" name="motivo" catalogo="motivo" model="motivo" />
                        <div x-show="motivo === 'otro'" x-cloak class="mt-3">
                            <label for="motivo_otro" class="text-xs text-gray-600">Especifique el motivo (opcional)</label>
                            <input type="text" name="motivo_otro" id="motivo_otro" maxlength="150" data-opcional="1" value="{{ old('motivo_otro') }}" class="{{ $input }}" autocomplete="off">
                        </div>
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="19" label="¿Se compromete a mantener las vacunas y la desparasitación al día?" campo="compromiso_vacunas">
                        <x-cuestionario.opciones tema="pol" name="compromiso_vacunas" catalogo="compromiso_vacunas" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="20" label="Si en algún momento ya no pudiera continuar cuidando a la mascota, ¿qué haría?" campo="si_no_puede"
                        nota="Permite conocer su planificación sin imponer una única respuesta.">
                        <x-cuestionario.opciones tema="pol" name="si_no_puede" catalogo="si_no_puede" model="sinopuede" columna />
                        <div x-show="sinopuede === 'otro'" x-cloak class="mt-3">
                            <label for="si_no_puede_otro" class="text-xs text-gray-600">Explique brevemente (opcional)</label>
                            <input type="text" name="si_no_puede_otro" id="si_no_puede_otro" maxlength="150" data-opcional="1" value="{{ old('si_no_puede_otro') }}" class="{{ $input }}" autocomplete="off">
                        </div>
                    </x-cuestionario.pregunta>
                </fieldset>

                {{-- Sección 6 · Visita y consentimientos --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Visita y consentimientos</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 6 de 6</p>
                    <h4 class="text-lg font-semibold text-gray-900">Visita y consentimientos</h4>
                    <p class="text-sm text-gray-600">Datos para coordinar la visita y compromisos obligatorios para enviar la solicitud.</p>

                    {{-- Dato de contacto sin número: no cuenta en el progreso ni en las alertas del cuestionario. --}}
                    <x-cuestionario.pregunta tema="pol" label="Teléfono de contacto (WhatsApp)" campo="telefono_contacto" ayuda="Obligatorio. La fundación te escribirá o llamará a este número para coordinar. Si lo cambias, también se actualiza en «Mi perfil».">
                        <input type="tel" name="telefono_contacto" id="telefono_contacto" maxlength="20" required value="{{ old('telefono_contacto', auth()->user()->telefono) }}" class="{{ $input }}" placeholder="Ej. 71234567" autocomplete="tel" inputmode="tel">
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="21" label="¿En qué zona vive aproximadamente?" campo="zona_aproximada" ayuda="No se solicita la dirección exacta.">
                        <input type="text" name="zona_aproximada" id="zona_aproximada" maxlength="100" value="{{ old('zona_aproximada') }}" class="{{ $input }}" placeholder="Ej. Queru Queru, Cochabamba" autocomplete="off">
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="22" label="¿En qué horarios tendría disponibilidad para una visita domiciliaria? (puede marcar varios)" campo="horarios_visita">
                        <x-cuestionario.opciones tema="pol" name="horarios_visita" catalogo="horarios_visita" multiple />
                    </x-cuestionario.pregunta>

                    <p class="pt-4 text-sm font-semibold text-gray-900">Consentimientos obligatorios</p>
                    <p class="text-xs text-gray-500">Sin marcar las 4 casillas no se puede enviar la solicitud.</p>
                    @foreach (C::CONSENTIMIENTOS as $campo => $texto)
                        <div class="py-2" data-num="{{ 22 + $loop->iteration }}">
                            <label for="{{ $campo }}" class="flex cursor-pointer items-start gap-3 text-sm text-gray-800">
                                <input type="checkbox" name="{{ $campo }}" id="{{ $campo }}" value="1" class="mt-0.5 rounded border-gray-300 text-blue-800 focus:ring-blue-700" @checked(old($campo))>
                                <span><span class="font-semibold text-blue-800">{{ 22 + $loop->iteration }}.</span> {{ $texto }}</span>
                            </label>
                            <x-input-error :messages="$errors->get($campo)" class="mt-1" />
                        </div>
                    @endforeach
                </fieldset>

                <div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button type="submit" class="rounded-full bg-amber-400 px-6 py-3 font-extrabold text-blue-950 hover:bg-amber-300">Enviar solicitud</button>
                        <a href="{{ route('mascotas.public.show', $mascota) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">Podrás ver el estado de tu solicitud en Mis solicitudes.</p>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
