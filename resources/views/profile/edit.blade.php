<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @php
                $nombreCompleto = trim($user->nombres.' '.$user->apellidos);
                $iniciales = mb_strtoupper(mb_substr($user->nombres, 0, 1).mb_substr($user->apellidos, 0, 1));
                $roles = ['ciudadano' => 'Ciudadano', 'voluntario' => 'Voluntario', 'administrador' => 'Administrador'];
            @endphp

            <section class="flex flex-col gap-4 rounded-2xl bg-blue-950 p-6 text-white sm:flex-row sm:items-center">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-amber-400 text-xl font-extrabold text-blue-950">
                    {{ $iniciales }}
                </div>
                <div>
                    <h3 class="text-xl font-extrabold">{{ $nombreCompleto }}</h3>
                    <p class="mt-1 text-sm text-sky-100">{{ $user->email }}</p>
                    <span class="mt-3 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white">{{ $roles[$user->rol] ?? $user->rol }}</span>
                </div>
            </section>

            <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="rounded-2xl border border-red-200 bg-red-50/50 p-4 shadow-sm sm:p-8">
                <p class="mb-4 text-xs font-bold uppercase tracking-wider text-red-700">Zona de riesgo</p>
                <div class="max-w-xl">
                    {{-- Solo un ciudadano activo puede eliminar su cuenta (UserPolicy::deleteOwnAccount). --}}
                    @can('deleteOwnAccount', $user)
                        @include('profile.partials.delete-user-form')
                    @else
                        <section>
                            <h2 class="text-lg font-medium text-gray-900">Eliminar cuenta</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $user->hasRole('administrador')
                                    ? 'Las cuentas de administración no se eliminan desde el perfil.'
                                    : 'Tu cuenta de voluntario no se puede eliminar desde el perfil. Comunícate con POL-Bolivia para gestionarla.' }}
                            </p>
                        </section>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
