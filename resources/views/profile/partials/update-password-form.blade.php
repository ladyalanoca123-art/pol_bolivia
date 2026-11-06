<section>
    {{-- El título y la descripción van en el encabezado de la tarjeta (profile/edit). --}}
    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div class="sm:max-w-sm">
            <label for="update_password_current_password" class="admin-etiqueta">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="admin-campo" autocomplete="current-password">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="update_password_password" class="admin-etiqueta">{{ __('New Password') }}</label>
                <input id="update_password_password" name="password" type="password" class="admin-campo" autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>
            <div>
                <label for="update_password_password_confirmation" class="admin-etiqueta">{{ __('Confirm Password') }}</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="admin-campo" autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-4 border-t border-slate-100 pt-5">
            <x-admin.boton variante="destacado">{{ __('Save') }} contraseña</x-admin.boton>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-sm font-semibold text-emerald-700">✓ {{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
