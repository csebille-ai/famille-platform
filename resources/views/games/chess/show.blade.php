<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4" id="chess-show"
        data-game-id="{{ $game->id }}"
        data-state-url="{{ route('games.chess.state', $game) }}"
        data-join-url="{{ route('games.chess.join', $game) }}"
        data-move-url="{{ route('games.chess.move', $game) }}">

        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Échecs</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Partie #{{ $game->id }}</div>
                </div>
                <a href="{{ route('games.chess.index') }}" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Retour</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Position</div>
                    <button type="button" id="chess-refresh" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Rafraîchir</button>
                </div>

                <div class="mt-3">
                    <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Échiquier</div>
                    <div id="chess-board" class="mt-2 grid grid-cols-8 gap-0 rounded-2xl overflow-hidden border border-[color:var(--fam-border-soft)] select-none" aria-label="Échiquier"></div>
                    <div class="mt-2 text-xs font-semibold text-[color:var(--fam-muted)]">Tape une pièce puis une case d’arrivée (promotion: on te demandera q/r/b/n).</div>
                </div>

                <div class="mt-2">
                    <div class="text-xs font-semibold text-[color:var(--fam-muted)]">FEN</div>
                    <div id="chess-fen" class="mt-1 text-xs font-semibold text-[color:var(--fam-text)] break-all">—</div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50" data-team="w" id="chess-join-w">Rejoindre Blancs</button>
                    <button type="button" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50" data-team="b" id="chess-join-b">Rejoindre Noirs</button>
                </div>

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Équipes</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-text)]">
                    <div><span class="text-[color:var(--fam-muted)]">Blancs:</span> <span id="chess-team-w">—</span></div>
                    <div><span class="text-[color:var(--fam-muted)]">Noirs:</span> <span id="chess-team-b">—</span></div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Jouer un coup</div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">Saisis un coup en UCI (ex: e2e4, g1f3, e7e8q).</div>

                <div class="mt-3 flex items-center gap-2">
                    <input id="chess-uci" type="text" inputmode="latin" autocapitalize="none" autocomplete="off" spellcheck="false"
                        placeholder="e2e4"
                        class="flex-1 rounded-xl border border-[color:var(--fam-border)] px-3 py-2 text-sm" />
                    <button type="button" id="chess-play" class="shrink-0 text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary)] text-white hover:opacity-90">Jouer</button>
                </div>

                <div id="chess-can-move" class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">—</div>

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Derniers coups</div>
                <ol id="chess-recent" class="mt-1 space-y-1 text-xs font-semibold text-[color:var(--fam-text)]"></ol>

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">PGN (simplifié)</div>
                <pre id="chess-pgn" class="mt-1 text-xs whitespace-pre-wrap text-[color:var(--fam-text)]">—</pre>
            </div>
        </div>

        <div id="chess-toast" class="hidden rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="text-xs font-semibold text-[color:var(--fam-text)]" id="chess-toast-msg"></div>
        </div>

    </div>

</x-app-layout>
