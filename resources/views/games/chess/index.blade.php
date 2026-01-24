<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4" id="chess-index" data-game-id="{{ $game?->id }}">
        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Échecs</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Partie asynchrone en équipe</div>
                </div>

                @if($game)
                    <a href="{{ route('games.chess.show', $game) }}" class="text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-primary-100)]/75">Ouvrir</a>
                @endif
            </div>
        </div>

        @if(!$game)
            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Aucune partie active</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">Reviens plus tard (V1 ne gère qu’une partie).</div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                    <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Tour</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full {{ $game->turn === 'w' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            Blancs
                        </span>
                        <span class="mx-2 text-[color:var(--fam-border)]">•</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full {{ $game->turn === 'b' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            Noirs
                        </span>
                    </div>

                    <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">PGN (simplifié)</div>
                    <pre class="mt-1 text-xs whitespace-pre-wrap text-[color:var(--fam-text)]">{{ $game->pgn ?: '—' }}</pre>
                </div>

                <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                    <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Ton rôle</div>

                    @if(!$myTeam)
                        <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">Spectateur</div>
                    @else
                        <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $myTeam === 'w' ? 'Équipe Blancs' : 'Équipe Noirs' }}</div>
                    @endif

                    <form method="POST" action="{{ route('games.chess.join', $game) }}" class="mt-3 space-y-2">
                        @csrf
                        <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Rejoindre une équipe</div>
                        <div class="flex flex-wrap gap-2">
                            <button name="team" value="w" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Blancs</button>
                            <button name="team" value="b" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Noirs</button>
                        </div>
                    </form>

                    <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Membres</div>
                    <div class="mt-1 text-xs font-semibold text-[color:var(--fam-text)]">
                        @php
                            $whiteNames = $members->where('team', 'w')->pluck('user.name')->filter()->join(', ');
                            $blackNames = $members->where('team', 'b')->pluck('user.name')->filter()->join(', ');
                        @endphp
                        <div>Blancs: {{ $whiteNames !== '' ? $whiteNames : '—' }}</div>
                        <div>Noirs: {{ $blackNames !== '' ? $blackNames : '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Jouer</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">La saisie des coups arrive sur la page “Ouvrir”.</div>
                <a href="{{ route('games.chess.show', $game) }}" class="mt-3 inline-flex text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary)] text-white hover:opacity-90">Ouvrir l’échiquier</a>
            </div>
        @endif
    </div>
</x-app-layout>
