<x-app-layout pageBgClass="fam-page-bg" :pageTitle="$puzzle->title">
    @php
        $fmt = function (int $ms): string {
            $ms = max(0, $ms);
            $s = (int) floor($ms / 1000);
            $m = (int) floor($s / 60);
            $r = $s % 60;
            return str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $r, 2, '0', STR_PAD_LEFT);
        };
    @endphp

    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4">
        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $puzzle->title }}</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Résultat — {{ (int) $attempt->grid_size }}×{{ (int) $attempt->grid_size }}</div>
                </div>

                <a href="{{ route('games.sliding-puzzles.show', $puzzle) }}" class="text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary)] text-white hover:opacity-90">Rejouer</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Ton temps</div>
                <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $fmt((int) $attempt->duration_ms) }}</div>

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Tes coups</div>
                <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ (int) $attempt->moves_count }}</div>

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Classement</div>
                <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">#{{ (int) $myRank }}</div>

                <div class="mt-3 flex gap-2">
                    <a href="{{ route('games.sliding-puzzles.leaderboard', $puzzle) }}" class="text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-primary-100)]/75">Voir le classement</a>
                    <a href="{{ route('games.sliding-puzzles.index') }}" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Tous les taquins</a>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Image</div>

                @if($imageUrl)
                    <div class="mt-2 rounded-2xl border border-[color:var(--fam-border)] bg-slate-100 overflow-hidden">
                        <img src="{{ $imageUrl }}" alt="Image du puzzle" class="w-full h-auto block" loading="lazy" />
                    </div>
                @else
                    <div class="mt-2 rounded-2xl border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-600">
                        <i class="ph ph-warning mr-1"></i> Aucune image configurée.
                    </div>
                @endif

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Terminé le</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-text)]">{{ optional($attempt->finished_at)->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
