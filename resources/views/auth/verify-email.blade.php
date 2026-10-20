<x-guest-layout titulo="Verifica tu correo">
    <p class="text-sm font-bold tracking-[0.14em] text-amber-700">UN ÚLTIMO PASO</p>
    <h1 class="mt-2 text-2xl font-extrabold leading-tight text-blue-950">Verifica tu correo</h1>
    <p class="mt-2 text-sm text-slate-600">¡Gracias por registrarte! Antes de comenzar, abre el enlace que te enviamos a tu correo electrónico. Si no lo recibiste, te enviaremos otro.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-700">
            Te enviamos un nuevo enlace de verificación al correo con el que te registraste.
        </div>
    @endif

    <div class="mt-6 flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm font-semibold text-blue-900 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 rounded-md">
                Cerrar sesión
            </button>
        </form>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>Reenviar el correo de verificación</x-primary-button>
        </form>
    </div>
</x-guest-layout>
