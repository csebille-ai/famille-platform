<form method="post" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    @method('put')

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-input-label for="update_password_current_password" :value="__('Mot de passe actuel')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Nouveau mot de passe')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmer')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center justify-between gap-3">
        <div class="microcopy text-xs text-[color:var(--fam-muted)]">Astuce: un mot de passe long + unique.</div>

        <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-white border border-[color:var(--fam-border)] text-[color:var(--fam-text)] text-sm font-semibold hover:bg-[color:var(--fam-tint)]">
            Mettre à jour
        </button>
    </div>

    @if (session('status') === 'password-updated')
        <p class="text-xs font-semibold text-emerald-700">Mot de passe mis à jour.</p>
    @endif
</form>
