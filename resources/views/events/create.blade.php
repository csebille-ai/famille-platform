<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-2xl mx-auto px-4 py-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-base font-semibold text-[color:var(--fam-text)]">Ajouter un événement</div>
            </div>
            <a href="{{ route('events.index') }}" class="shrink-0 inline-flex items-center h-10 px-3 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-semibold text-[color:var(--fam-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Retour</a>
        </div>

        <form method="POST" action="{{ route('events.store') }}">
            @csrf

            @include('events._form', ['defaults' => $defaults ?? []])

            <div class="sticky bottom-[calc(env(safe-area-inset-bottom)+0.75rem)]">
                <div class="mt-3 rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-3 flex items-center justify-between gap-3">
                    <a href="{{ route('events.index') }}" class="inline-flex items-center justify-center h-11 px-4 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-semibold text-[color:var(--fam-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Annuler</a>
                    <button type="submit" class="inline-flex items-center justify-center h-11 px-5 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)] active:scale-[0.99]">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
