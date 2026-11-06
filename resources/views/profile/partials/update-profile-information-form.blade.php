<section>
    {{-- El título y la descripción van en el encabezado de la tarjeta (profile/edit). --}}
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    {{-- "Contraseña actual" solo aparece si se cambia el correo (ProfileUpdateRequest también lo exige en el servidor). --}}
    @php
        $pedirClave = mb_strtolower((string) old('email', $user->email)) !== mb_strtolower($user->email) || $errors->has('current_password');
    @endphp
    <form method="post" action="{{ route('profile.update') }}" class="space-y-5"
          x-data="{ correoActual: @js(mb_strtolower($user->email)), correo: @js(old('email', $user->email)) }">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="nombres" class="admin-etiqueta">{{ __('Nombres') }}</label>
                <input id="nombres" name="nombres" type="text" class="admin-campo" value="{{ old('nombres', $user->nombres) }}" required autofocus autocomplete="given-name">
                <x-input-error class="mt-2" :messages="$errors->get('nombres')" />
            </div>
            <div>
                <label for="apellidos" class="admin-etiqueta">{{ __('Apellidos') }}</label>
                <input id="apellidos" name="apellidos" type="text" class="admin-campo" value="{{ old('apellidos', $user->apellidos) }}" required autocomplete="family-name">
                <x-input-error class="mt-2" :messages="$errors->get('apellidos')" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="email" class="admin-etiqueta">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" class="admin-campo" value="{{ old('email', $user->email) }}" x-model="correo" required autocomplete="username">
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div>
                        <p class="mt-2 text-sm text-slate-800">
                            {{ __('Your email address is unverified.') }}
                            <button form="send-verification" class="rounded-md text-sm text-blue-800 underline hover:text-blue-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>
                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-emerald-700">{{ __('A new verification link has been sent to your email address.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
            <div>
                <label for="telefono" class="admin-etiqueta">Teléfono (opcional)</label>
                <input id="telefono" name="telefono" type="tel" class="admin-campo" value="{{ old('telefono', $user->telefono) }}" autocomplete="tel" placeholder="Ej. 71234567">
                <p class="admin-ayuda">Lo usamos para coordinar por WhatsApp.</p>
                <x-input-error class="mt-2" :messages="$errors->get('telefono')" />
            </div>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4" x-show="correo.trim().toLowerCase() !== correoActual" @unless ($pedirClave) x-cloak @endunless>
            <label for="profile_current_password" class="admin-etiqueta">Contraseña actual</label>
            <input id="profile_current_password" name="current_password" type="password" class="admin-campo" autocomplete="current-password">
            <p class="mt-1 text-sm text-amber-900">Para cambiar tu correo, confirma que eres tú con tu contraseña actual.</p>
            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
        </div>

        <div class="flex items-center gap-4 border-t border-slate-100 pt-5">
            <x-admin.boton>{{ __('Save') }} cambios</x-admin.boton>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-sm font-semibold text-emerald-700">✓ {{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
