<x-guest-layout titulo="Elige una nueva contraseña">
    <p class="text-sm font-bold tracking-[0.14em] text-amber-700">RECUPERA TU CUENTA</p>
    <h1 class="mt-2 text-2xl font-extrabold leading-tight text-blue-950">Elige una nueva contraseña</h1>
    <p class="mt-2 text-sm text-slate-600">Debe tener al menos 8 caracteres, con mayúsculas, minúsculas y números.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6">
        @csrf

        {{-- Token del enlace --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        {{-- Correo electrónico --}}
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Contraseña --}}
        <div class="mt-4">
            <x-input-label for="password" value="Nueva contraseña" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Confirmación --}}
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirma la nueva contraseña" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6 flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-blue-900 hover:underline">Pedir un enlace nuevo</a>
            <x-primary-button>Guardar contraseña</x-primary-button>
        </div>
    </form>
</x-guest-layout>
