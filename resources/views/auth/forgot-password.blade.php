<x-guest-layout titulo="¿Olvidaste tu contraseña?">
    <p class="text-sm font-bold tracking-[0.14em] text-amber-700">RECUPERA TU CUENTA</p>
    <h1 class="mt-2 text-2xl font-extrabold leading-tight text-blue-950">¿Olvidaste tu contraseña?</h1>
    <p class="mt-2 text-sm text-slate-600">Escribe el correo con el que te registraste y te enviaremos un enlace para elegir una nueva contraseña.</p>

    {{-- Estado de la solicitud --}}
    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6">
        @csrf

        {{-- Correo electrónico --}}
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-6 flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-blue-900 hover:underline">Volver a iniciar sesión</a>
            <x-primary-button>Enviar enlace</x-primary-button>
        </div>
    </form>
</x-guest-layout>
