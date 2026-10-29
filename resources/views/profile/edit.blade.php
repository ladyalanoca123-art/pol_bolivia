@php
    $nombreCompleto = trim($user->nombres.' '.$user->apellidos);
    $iniciales = mb_strtoupper(mb_substr($user->nombres, 0, 1).mb_substr($user->apellidos, 0, 1));
    $roles = ['ciudadano' => 'Ciudadano/a', 'voluntario' => 'Voluntario/a', 'administrador' => 'Administrador/a'];
    $tarjeta = 'rounded-2xl border border-slate-200 bg-white p-5 sm:p-8';
@endphp
<x-admin.pagina titulo="Mi perfil" seccion="Mi cuenta" descripcion="Actualiza tus datos de contacto y tu contraseña." ancho="max-w-6xl" :avisos="false">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Resumen de la cuenta --}}
        <aside class="min-w-0 lg:sticky lg:top-6 lg:self-start">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="bg-[#0d2138] px-6 pb-12 pt-6 text-white">
                    <p class="text-sm font-medium text-amber-300">{{ $roles[$user->rol] ?? $user->rol }}</p>
                </div>
                <div class="-mt-10 px-6 pb-6">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-white bg-amber-400 text-2xl font-extrabold text-blue-950">{{ $iniciales }}</div>
                    <h2 class="mt-3 text-xl font-bold text-blue-950">{{ $nombreCompleto }}</h2>
                    <p class="mt-1 break-all text-sm text-slate-600">{{ $user->email }}</p>
                    <dl class="mt-5 space-y-3 border-t border-slate-100 pt-4">
                        <x-admin.dato etiqueta="Teléfono (WhatsApp)">
                            @if ($user->telefono){{ $user->telefono }}@else<span class="text-amber-700">Sin registrar</span>@endif
                        </x-admin.dato>
                        <x-admin.dato etiqueta="Miembro desde">{{ $user->created_at->translatedFormat('F \d\e Y') }}</x-admin.dato>
                    </dl>
                    @unless ($user->hasRole('administrador'))
                        <x-admin.boton variante="secundario" tamano="chico" class="mt-5 w-full" :href="route('inicio')">Volver a Mi espacio</x-admin.boton>
                    @endunless
                </div>
            </section>
        </aside>

        {{-- Formularios --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <div class="{{ $tarjeta }}">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="{{ $tarjeta }}">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="rounded-2xl border border-red-200 bg-red-50/50 p-5 sm:p-8">
                <p class="mb-4 text-sm font-semibold text-red-700">Zona de riesgo</p>
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
</x-admin.pagina>
