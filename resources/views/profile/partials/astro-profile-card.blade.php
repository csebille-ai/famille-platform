@php
    /** @var \App\Models\User $user */
    $p = $user->astroProfile;
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-xs text-slate-500">Astro (fun)</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">Fiche astrale</div>
        </div>

        @if($p && $p->computed_at)
            <div class="text-xs text-slate-500">Maj {{ $p->computed_at->diffForHumans() }}</div>
        @endif
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs text-slate-500">Signature</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $p->signature ?? 'Profil en cours (ajoute date/heure/lieu)' }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs text-slate-500">Archétype</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $p->archetype ?? '—' }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Talents</div>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach(($p->talents ?? []) as $t)
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ $t }}</span>
                @endforeach
                @if(empty($p?->talents))
                    <span class="text-sm text-slate-500">—</span>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Point de vigilance</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $p->weakness ?? '—' }}</div>
        </div>
    </div>

    <div class="mt-4 text-xs text-slate-500">
        Note: le thème natal (planètes/maisons) n’est pas encore calculé automatiquement sans provider externe.
    </div>
</div>
