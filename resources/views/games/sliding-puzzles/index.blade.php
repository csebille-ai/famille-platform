<x-app-layout pageBgClass="fam-page-bg" pageTitle="Taquin">
    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4">
        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Taquin</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Glisse les tuiles pour reconstituer l’image</div>
                </div>
                @can('manage-users')
                    <a href="{{ route('admin.sliding-puzzles.index') }}" class="text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-primary-100)]/75 shrink-0">Admin</a>
                @endcan            </div>
        </div>

        @if($puzzles->isEmpty())
            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Aucun puzzle disponible</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">Crée un puzzle dans la table sliding_puzzles.</div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($puzzles as $p)
                    @php($img = $p->imageUrl())
                    <a href="{{ route('games.sliding-puzzles.show', $p) }}" class="block rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                                <i class="ph ph-squares-four text-[20px]" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $p->title }}</div>
                                    <div class="text-[10px] font-semibold rounded-full px-2 py-1 border border-[color:var(--fam-border)] bg-white text-[color:var(--fam-muted)] shrink-0">{{ (int) $p->grid_size }}×{{ (int) $p->grid_size }}</div>
                                </div>
                                @if($p->description)
                                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">{{ $p->description }}</div>
                                @else
                                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">—</div>
                                @endif

                                @if($img)
                                    <div class="mt-2">
                                        <div class="h-20 w-full rounded-xl border border-[color:var(--fam-border)] bg-slate-100 overflow-hidden" style="background-image:url('{{ $img }}'); background-size:cover; background-position:center;"></div>
                                    </div>
                                @endif
                            </div>

                            <div class="text-[color:var(--fam-muted)] mt-1"><i class="ph ph-caret-right" aria-hidden="true"></i></div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
