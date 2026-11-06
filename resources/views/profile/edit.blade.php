@php
    $nombreCompleto = trim($user->nombres.' '.$user->apellidos);
    $iniciales = mb_strtoupper(mb_substr($user->nombres, 0, 1).mb_substr($user->apellidos, 0, 1));
    $roles = ['ciudadano' => 'Ciudadano/a', 'voluntario' => 'Voluntario/a', 'administrador' => 'Administrador/a'];
    // Color del rol, el mismo de la lista de Usuarios: morado, ámbar o verde.
    $colorRol = ['administrador' => 'bg-indigo-100 text-indigo-900 ring-indigo-300', 'voluntario' => 'bg-amber-100 text-amber-900 ring-amber-300', 'ciudadano' => 'bg-emerald-100 text-emerald-800 ring-emerald-300'][$user->rol] ?? 'bg-slate-100 text-slate-700 ring-slate-300';
    $tarjeta = 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm';
    $icono = fn (string $trazo, string $color) => '<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl '.$color.'" aria-hidden="true"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="'.$trazo.'" /></svg></span>';
@endphp
<x-admin.pagina titulo="Mi perfil" seccion="Mi cuenta" descripcion="Actualiza tus datos de contacto y tu contraseña." ancho="max-w-6xl" :avisos="false">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Tarjeta de la cuenta: franja azul con la inicial grande y los datos principales. --}}
        <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start">
            <section class="{{ $tarjeta }}">
                <div class="relative bg-gradient-to-br from-blue-950 via-blue-900 to-blue-800 px-6 pb-14 pt-6 text-white">
                    <span class="absolute -end-6 -top-6 h-28 w-28 rounded-full bg-amber-400/20" aria-hidden="true"></span>
                    <span class="absolute end-10 top-10 h-10 w-10 rounded-full bg-amber-300/25" aria-hidden="true"></span>
                    <p class="relative text-[13px] font-semibold uppercase tracking-[0.14em] text-amber-300">Mi cuenta</p>
                    <p class="relative mt-1 text-sm text-blue-100">POL-Bolivia</p>
                </div>
                <div class="-mt-11 px-6 pb-6">
                    <div class="relative z-10 flex h-[88px] w-[88px] items-center justify-center rounded-full bg-amber-400 text-3xl font-extrabold text-blue-950 shadow-md ring-4 ring-white">{{ $iniciales }}</div>
                    <h2 class="mt-3 text-xl font-bold leading-tight text-blue-950">{{ $nombreCompleto }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[13px] font-semibold ring-1 ring-inset {{ $colorRol }}">{{ $roles[$user->rol] ?? $user->rol }}</span>
                        @if ($user->es_principal)<span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-[13px] font-semibold text-amber-900 ring-1 ring-inset ring-amber-300">Principal</span>@endif
                    </div>

                    <ul class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-sm">
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-800" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4zM4 7l8 6 8-6" /></svg></span>
                            <span class="min-w-0"><span class="block text-[13px] text-slate-500">Correo electrónico</span><span class="block break-all text-[13px] font-semibold text-blue-950">{{ $user->email }}</span></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z" /></svg></span>
                            <span class="min-w-0"><span class="block text-[13px] text-slate-500">Teléfono (WhatsApp)</span>
                                @if ($user->telefono)<span class="block font-semibold text-blue-950">{{ $user->telefono }}</span>@else<span class="block font-semibold text-amber-700">Sin registrar</span>@endif
                            </span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></svg></span>
                            <span class="min-w-0"><span class="block text-[13px] text-slate-500">Miembro desde</span><span class="block font-semibold text-blue-950">{{ \Illuminate\Support\Str::ucfirst($user->created_at->translatedFormat('F \d\e Y')) }}</span></span>
                        </li>
                    </ul>
                    @unless ($user->hasRole('administrador'))
                        <x-admin.boton variante="secundario" tamano="chico" class="mt-5 w-full" :href="route('inicio')">Volver a Mi espacio</x-admin.boton>
                    @endunless
                </div>
            </section>
        </aside>

        {{-- Formularios, cada uno con su encabezado de color --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <section class="{{ $tarjeta }}">
                <div class="flex items-center gap-3 border-b border-slate-100 bg-blue-50/60 px-5 py-4 sm:px-8">
                    {!! $icono('M15.5 7.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0', 'bg-blue-950 text-amber-300') !!}
                    <div>
                        <h2 class="text-lg font-semibold text-blue-950">{{ __('Profile Information') }}</h2>
                        <p class="text-sm text-slate-600">Tu nombre, tu correo y tu teléfono de contacto.</p>
                    </div>
                </div>
                <div class="p-5 sm:p-8">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>

            <section class="{{ $tarjeta }}">
                <div class="flex items-center gap-3 border-b border-slate-100 bg-amber-50/70 px-5 py-4 sm:px-8">
                    {!! $icono('M7 11V8a5 5 0 0 1 10 0v3M6 11h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Zm6 4v2', 'bg-amber-400 text-blue-950') !!}
                    <div>
                        <h2 class="text-lg font-semibold text-blue-950">{{ __('Update Password') }}</h2>
                        <p class="text-sm text-slate-600">Usa una contraseña larga y que no uses en otros sitios.</p>
                    </div>
                </div>
                <div class="p-5 sm:p-8">
                    @include('profile.partials.update-password-form')
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-red-100 bg-red-50 px-5 py-4 sm:px-8">
                    {!! $icono('M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z', 'bg-red-100 text-red-700') !!}
                    <div>
                        <h2 class="text-lg font-semibold text-red-800">Zona de riesgo</h2>
                        <p class="text-sm text-red-700/80">Acciones que no se pueden deshacer.</p>
                    </div>
                </div>
                <div class="p-5 sm:p-8">
                    {{-- Solo un ciudadano activo puede eliminar su cuenta (UserPolicy::deleteOwnAccount). --}}
                    @can('deleteOwnAccount', $user)
                        @include('profile.partials.delete-user-form')
                    @else
                        <section>
                            <h3 class="text-base font-semibold text-blue-950">Eliminar cuenta</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $user->hasRole('administrador')
                                    ? 'Las cuentas de administración no se eliminan desde el perfil.'
                                    : 'Tu cuenta de voluntario no se puede eliminar desde el perfil. Comunícate con POL-Bolivia para gestionarla.' }}
                            </p>
                        </section>
                    @endcan
                </div>
            </section>
        </div>
    </div>
</x-admin.pagina>
