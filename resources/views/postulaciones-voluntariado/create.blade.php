@use('App\Support\CuestionarioVoluntariado', 'V')
<x-app-layout>
    @php
        $sec = 'rounded-lg border border-gray-200 bg-white p-6 shadow-sm';
        $input = 'mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-700 focus:ring-blue-700';
    @endphp

    <x-cuestionario.progreso />

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav aria-label="Ruta de navegación" class="mb-4 text-sm">
                <ol class="flex flex-wrap items-center gap-1 text-gray-600">
                    <li><a href="{{ route('postulaciones-voluntariado.index') }}" class="font-medium text-blue-800 hover:text-blue-950 hover:underline">Voluntariado</a></li>
                    <li aria-hidden="true" class="text-gray-400">›</li>
                    <li aria-current="page" class="font-semibold text-blue-950">Nueva postulación</li>
                </ol>
            </nav>

            <div class="mb-6 flex flex-col gap-4 rounded-lg bg-blue-950 p-6 text-white sm:flex-row sm:items-center">
                <img src="{{ asset('images/pol-bolivia/grupo-voluntarios.jpg') }}" alt="Grupo de voluntarios de POL-Bolivia" class="h-16 w-16 sm:h-20 sm:w-20 rounded-xl object-cover ring-2 ring-amber-400">
                <div class="min-w-0">
                <h3 class="text-2xl font-bold">Postulación de voluntariado</h3>
                <p class="mt-1 text-sm text-sky-100">Únete al equipo que rescata, cuida y acompaña a las mascotas en adopción. La fundación revisará tu postulación y te contactará.</p>
                </div>
            </div>

            @if ($tienePendiente)
                {{-- Ya hay una postulación pendiente: no se muestra el formulario (la validación del servidor sigue como respaldo). --}}
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-6 text-center">
                    <h3 class="text-lg font-bold text-blue-950">Ya tienes una postulación en revisión</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-700">La fundación está revisando tu información. Podrás enviar otra postulación cuando responda.</p>
                    <a href="{{ route('postulaciones-voluntariado.index') }}" class="mt-5 inline-block rounded-full bg-amber-400 px-6 py-3 font-extrabold text-blue-950 hover:bg-amber-300">Ver mi postulación</a>
                </div>
            @else
            @if ($errors->has('postulacion'))
                <p class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('postulacion') }}</p>
            @elseif ($errors->any())
                <p class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">Revisa las preguntas marcadas en rojo antes de enviar.</p>
            @endif

            <form method="POST" action="{{ route('postulaciones-voluntariado.store') }}" class="space-y-6"
                  x-data="cuestionarioFormulario({ experiencia: @js(old('experiencia_previa')) })"
                  x-on:input="$nextTick(() => progreso())" x-on:change="$nextTick(() => progreso())">
                @csrf

                <div class="sticky top-0 z-10 -mx-1 rounded-md bg-gray-100/95 px-3 py-2 text-sm text-gray-700 shadow-sm" aria-live="polite">
                    <div class="flex justify-between gap-3"><span x-text="texto"></span><span x-text="porcentaje + ' %'"></span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-300"><div class="h-full bg-amber-400 transition-all" :style="`width: ${porcentaje}%`"></div></div>
                </div>

                {{-- Sección 1 · Motivación y experiencia --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Motivación y experiencia</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 1 de 3</p>
                    <h4 class="text-lg font-semibold text-gray-900">Motivación y experiencia</h4>
                    <p class="text-sm text-gray-600">Queremos conocer por qué te interesa ayudar y qué experiencia tienes.</p>

                    <x-cuestionario.pregunta tema="pol" num="1" label="¿Por qué quiere ser voluntario/a?" campo="motivacion">
                        <textarea name="motivacion" id="motivacion" rows="3" maxlength="5000" class="{{ $input }}" placeholder="Ej. Quiero ayudar a que más animales encuentren un hogar responsable.">{{ old('motivacion') }}</textarea>
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="2" label="¿Tiene experiencia previa con animales o en fundaciones?" campo="experiencia_previa">
                        <x-cuestionario.opciones tema="pol" name="experiencia_previa" :opciones="V::OPCIONES['experiencia_previa']" model="experiencia" />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="3" label="Descríbala brevemente" campo="experiencia_detalle" ayuda="Aparece porque respondió que sí." condicional x-show="experiencia === 'si'" x-cloak>
                        <textarea name="experiencia_detalle" id="experiencia_detalle" rows="3" maxlength="1000" class="{{ $input }}" placeholder="Ej. Cuidé perros rescatados durante un año en un refugio de mi barrio.">{{ old('experiencia_detalle') }}</textarea>
                    </x-cuestionario.pregunta>
                </fieldset>

                {{-- Sección 2 · Áreas y disponibilidad --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Áreas y disponibilidad</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 2 de 3</p>
                    <h4 class="text-lg font-semibold text-gray-900">Áreas y disponibilidad</h4>
                    <p class="text-sm text-gray-600">Con esto la fundación sabe en qué puedes ayudar y cuándo llamarte.</p>

                    <x-cuestionario.pregunta tema="pol" num="4" label="¿En qué áreas le gustaría colaborar? (puede marcar varias)" campo="areas_interes">
                        <x-cuestionario.opciones tema="pol" name="areas_interes" :opciones="V::OPCIONES['areas_interes']" multiple />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="5" label="¿Qué días tiene disponibles? (puede marcar varios)" campo="dias_disponibles">
                        <x-cuestionario.opciones tema="pol" name="dias_disponibles" :opciones="V::OPCIONES['dias_disponibles']" multiple />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="6" label="¿En qué franja horaria? (puede marcar varias)" campo="franjas_horarias">
                        <x-cuestionario.opciones tema="pol" name="franjas_horarias" :opciones="V::OPCIONES['franjas_horarias']" multiple />
                    </x-cuestionario.pregunta>

                    <x-cuestionario.pregunta tema="pol" num="7" label="Zona de preferencia" campo="zona_preferencia" ayuda="La zona o barrio donde le resulta más fácil colaborar.">
                        <input type="text" name="zona_preferencia" id="zona_preferencia" maxlength="100" value="{{ old('zona_preferencia') }}" class="{{ $input }}" placeholder="Ej. Zona Norte, Cochabamba" autocomplete="off">
                    </x-cuestionario.pregunta>
                </fieldset>

                {{-- Sección 3 · Compromiso --}}
                <fieldset class="{{ $sec }}">
                    <legend class="sr-only">Compromiso</legend>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-800">Sección 3 de 3</p>
                    <h4 class="text-lg font-semibold text-gray-900">Compromiso</h4>
                    <p class="text-sm text-gray-600">Confirmaciones obligatorias para enviar la postulación.</p>

                    <x-cuestionario.pregunta tema="pol" num="8" label="Habilidades útiles (opcional)" campo="habilidades" ayuda="Por ejemplo: fotografía, diseño, primeros auxilios, manejo de redes." data-opcional>
                        <textarea name="habilidades" id="habilidades" rows="2" maxlength="5000" class="{{ $input }}" placeholder="Ej. Sé tomar buenas fotos y manejar Instagram">{{ old('habilidades') }}</textarea>
                    </x-cuestionario.pregunta>

                    <p class="pt-2 text-xs text-gray-500">Sin marcar las 2 casillas no se puede enviar la postulación.</p>
                    @foreach (['confirma_mayor_edad' => 'Confirmo que soy mayor de 18 años.', 'declara_informacion_veraz' => 'Declaro que la información proporcionada es verdadera.'] as $campo => $texto)
                        <div class="py-2" data-num="{{ 8 + $loop->iteration }}">
                            <label for="{{ $campo }}" class="flex cursor-pointer items-start gap-3 text-sm text-gray-800">
                                <input type="checkbox" name="{{ $campo }}" id="{{ $campo }}" value="1" class="mt-0.5 rounded border-gray-300 text-blue-800 focus:ring-blue-700" @checked(old($campo))>
                                <span><span class="font-semibold text-blue-800">{{ 8 + $loop->iteration }}.</span> {{ $texto }}</span>
                            </label>
                            <x-input-error :messages="$errors->get($campo)" class="mt-1" />
                        </div>
                    @endforeach
                </fieldset>

                <div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button type="submit" class="rounded-full bg-amber-400 px-6 py-3 font-extrabold text-blue-950 hover:bg-amber-300">Enviar postulación</button>
                        <a href="{{ route('postulaciones-voluntariado.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">Podrás ver el estado de tu postulación en Voluntariado.</p>
                </div>
            </form>
            @endif
        </div>
    </div>
</x-app-layout>
