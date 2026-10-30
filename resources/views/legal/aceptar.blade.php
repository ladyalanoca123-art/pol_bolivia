{{--
    Cuentas que todavía no aceptaron los Términos y la Política de privacidad vigentes (creadas antes de que
    existieran, o después de un cambio de versión). App\Http\Middleware\ExigirAceptacionLegal las trae aquí.
--}}
<x-guest-layout>
    <h1 class="text-xl font-bold text-blue-950">Antes de continuar</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">
        Publicamos nuestros <strong>Términos y condiciones</strong> y nuestra <strong>Política de privacidad</strong>.
        Para seguir usando tu cuenta, léelos y acéptalos.
    </p>

    <form method="POST" action="{{ route('legal.aceptar.guardar') }}" class="mt-5 space-y-3">
        @csrf
        @foreach ([
            'acepta_terminos' => ['Tengo '.config('pol.legal.edad_minima').' años o más y acepto los', 'Términos y condiciones', route('legal.terminos')],
            'acepta_privacidad' => ['Acepto la', 'Política de privacidad', route('legal.privacidad')],
        ] as $campo => [$texto, $documento, $enlace])
            <div>
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="{{ $campo }}" value="1" required @checked(old($campo))
                           class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-blue-900 focus:ring-blue-700/30"
                           @error($campo) aria-invalid="true" aria-describedby="{{ $campo }}-error" @enderror>
                    <span>{{ $texto }} <a href="{{ $enlace }}" target="_blank" rel="noopener" class="font-semibold text-blue-800 underline">{{ $documento }}<span class="sr-only"> (se abre en una pestaña nueva)</span></a>.</span>
                </label>
                @error($campo)<p id="{{ $campo }}-error" class="ml-8 mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <button type="submit" class="mt-2 inline-flex h-11 w-full items-center justify-center rounded-full bg-amber-400 px-6 text-sm font-bold text-blue-950 hover:bg-amber-300 focus:outline-none focus:ring-4 focus:ring-amber-400/40">Aceptar y continuar</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="text-sm text-slate-500 underline hover:text-slate-700">Cerrar sesión</button>
    </form>
</x-guest-layout>
