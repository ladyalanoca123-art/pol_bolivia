<x-guest-layout titulo="Confirma tu contraseña">
    <p class="text-sm font-bold tracking-[0.14em] text-amber-700">ZONA PROTEGIDA</p>
    <h1 class="mt-2 text-2xl font-extrabold leading-tight text-blue-950">Confirma tu contraseña</h1>
    <p class="mt-2 text-sm text-slate-600">Esta es una sección protegida. Escribe tu contraseña para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6">
        @csrf

        {{-- Contraseña --}}
        <div>
            <x-input-label for="password" value="Contraseña" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>Confirmar</x-primary-button>
        </div>
    </form>
</x-guest-layout>
