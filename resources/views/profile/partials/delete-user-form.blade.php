<section>
    <header>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-[color:var(--fam-text)]">Supprimer le compte</h2>
                <p class="microcopy mt-1 text-xs text-[color:var(--fam-muted)]">Action irréversible. Toutes tes données seront supprimées.</p>
            </div>
        </div>
    </header>

    <div class="mt-4 flex items-center justify-between gap-3">
        <div class="text-xs text-[color:var(--fam-muted)]">Une confirmation + ton mot de passe seront demandés.</div>
        <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-white border border-rose-200 text-rose-700 text-sm font-semibold hover:bg-rose-50">
            Supprimer
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">Supprimer le compte ?</h2>

            <p class="microcopy mt-1 text-sm text-gray-600">Cette action est définitive. Entre ton mot de passe pour confirmer.</p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-white border border-rose-200 text-rose-700 text-sm font-semibold hover:bg-rose-50">
                    Supprimer
                </button>
            </div>
        </form>
    </x-modal>
</section>
