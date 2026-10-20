<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    {{-- "Contraseña actual" solo aparece si se cambia el correo (ProfileUpdateRequest también lo exige en el servidor). --}}
    @php($pedirClave = mb_strtolower((string) old('email', $user->email)) !== mb_strtolower($user->email) || $errors->has('current_password'))
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6"
          x-data="{ correoActual: @js(mb_strtolower($user->email)), correo: @js(old('email', $user->email)) }">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="nombres" :value="__('Nombres')" />
            <x-text-input
                id="nombres"
                name="nombres"
                type="text"
                class="mt-1 block w-full"
                :value="old('nombres', $user->nombres)"
                required
                autofocus
                autocomplete="given-name"
            />
            <x-input-error class="mt-2" :messages="$errors->get('nombres')" />
        </div>

        <div>
            <x-input-label for="apellidos" :value="__('Apellidos')" />
            <x-text-input
                id="apellidos"
                name="apellidos"
                type="text"
                class="mt-1 block w-full"
                :value="old('apellidos', $user->apellidos)"
                required
                autocomplete="family-name"
            />
            <x-input-error class="mt-2" :messages="$errors->get('apellidos')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full"
                :value="old('email', $user->email)"
                x-model="correo"
                required
                autocomplete="username"
            />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div x-show="correo.trim().toLowerCase() !== correoActual" @unless ($pedirClave) x-cloak @endunless>
            <x-input-label for="profile_current_password" value="Contraseña actual" />
            <x-text-input id="profile_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <p class="mt-1 text-sm text-gray-600">Para cambiar tu correo, confirma que eres tú con tu contraseña actual.</p>
            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
        </div>

        <div>
            <x-input-label for="telefono" value="Teléfono (opcional)" />
            <x-text-input
                id="telefono"
                name="telefono"
                type="tel"
                class="mt-1 block w-full"
                :value="old('telefono', $user->telefono)"
                autocomplete="tel"
                placeholder="Ej. 71234567"
            />
            <x-input-error class="mt-2" :messages="$errors->get('telefono')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-700"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
