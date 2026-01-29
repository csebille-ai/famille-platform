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
                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">Classement — {{ $puzzle->title }}</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Meilleurs temps (1 score par personne) — {{ (int) $gridSize }}×{{ (int) $gridSize }}</div>
                </div>

                <a href="{{ route('games.sliding-puzzles.show', $puzzle) }}" class="text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary)] text-white hover:opacity-90">Jouer</a>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Ton meilleur</div>
                @if($myBest)
                    <div class="text-xs font-semibold text-[color:var(--fam-text)]">{{ $fmt((int) $myBest->duration_ms) }} — {{ (int) $myBest->moves_count }} coups</div>
                @else
                    <div class="text-xs font-semibold text-[color:var(--fam-text)]">—</div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
            @if($bestPerUser->isEmpty())
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Aucun score pour l’instant</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">Sois le premier !</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead>
                            <tr class="text-xs font-semibold text-[color:var(--fam-muted)]">
                                <th class="py-2 pr-3">#</th>
                                <th class="py-2 pr-3">Joueur</th>
                                <th class="py-2 pr-3">Temps</th>
                                <th class="py-2 pr-3">Coups</th>
                                <th class="py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-semibold text-[color:var(--fam-text)]">
                            @foreach($bestPerUser as $i => $a)
                                <tr class="border-t border-[color:var(--fam-border)]">
                                    <td class="py-2 pr-3">{{ $i + 1 }}</td>
                                    <td class="py-2 pr-3">{{ $a->user?->name ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $fmt((int) $a->duration_ms) }}</td>
                                    <td class="py-2 pr-3">{{ (int) $a->moves_count }}</td>
                                    <td class="py-2">{{ optional($a->finished_at)->format('d/m H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div>
            <a href="{{ route('games.sliding-puzzles.index') }}" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50 inline-flex">← Tous les taquins</a>
        </div>
    </div>
</x-app-layout>
