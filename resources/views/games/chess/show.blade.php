<x-app-layout pageBgClass="fam-page-bg" pageTitle="Échecs">
    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4" id="chess-show"
        data-game-id="{{ $game->id }}"
        data-state-url="{{ route('games.chess.state', $game) }}"
        data-join-url="{{ route('games.chess.join', $game) }}"
        data-move-url="{{ route('games.chess.move', $game) }}">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="space-y-3">
                <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Partie</div>
                            <div id="chess-turn" class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">—</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="chess-cancel" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Annuler</button>
                            <button type="button" id="chess-refresh" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50">Rafraîchir</button>
                        </div>
                    </div>

                    <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Ton équipe</div>
                    <div id="chess-my-team" class="mt-0.5 text-sm font-semibold text-[color:var(--fam-text)]">—</div>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button type="button" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50" data-team="w" id="chess-join-w">Rejoindre Blancs</button>
                        <button type="button" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50" data-team="b" id="chess-join-b">Rejoindre Noirs</button>
                    </div>

                    <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Équipes</div>
                    <div class="mt-1 text-xs font-semibold text-[color:var(--fam-text)]">
                        <div><span class="text-[color:var(--fam-muted)]">Blancs:</span> <span id="chess-team-w">—</span></div>
                        <div><span class="text-[color:var(--fam-muted)]">Noirs:</span> <span id="chess-team-b">—</span></div>
                    </div>

                    <div id="chess-can-move" class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">—</div>
                </div>

                <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Échiquier</div>
                    </div>

                    <style>
                        /* Scoped helpers: avoid browser default outline (black lines) */
                        #chess-board .chess-last-move { box-shadow: inset 0 0 0 2px rgba(251, 191, 36, .85); }
                        #chess-board .chess-in-check { box-shadow: inset 0 0 0 2px rgba(251, 113, 133, .85); }
                    </style>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Noirs — prises</div>
                        <div id="chess-captures-b" class="flex flex-wrap justify-end gap-1 text-base leading-none text-[color:var(--fam-text)]">—</div>
                    </div>

                    <div id="chess-board" class="mt-2 relative w-full aspect-square grid grid-cols-8 grid-rows-8 gap-0 rounded-2xl overflow-hidden border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-alt)] select-none touch-manipulation" aria-label="Échiquier">
                        @php
                            $files = ['a','b','c','d','e','f','g','h'];
                        @endphp
                        @for($rank = 8; $rank >= 1; $rank--)
                            @foreach($files as $idx => $file)
                                @php
                                    // a1 is dark.
                                    $fileNum = $idx + 1;
                                    $isDark = (($fileNum + $rank) % 2) === 0;
                                @endphp
                                <div class="aspect-square {{ $isDark ? 'bg-slate-200' : 'bg-white' }}"></div>
                            @endforeach
                        @endfor

                        <div id="chess-board-loading" class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-[color:var(--fam-muted)] bg-white/40 pointer-events-none">
                            Chargement de l’échiquier…
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Blancs — prises</div>
                        <div id="chess-captures-w" class="flex flex-wrap justify-end gap-1 text-base leading-none text-[color:var(--fam-text)]">—</div>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Derniers coups</div>
                    </div>
                    <ol id="chess-recent" class="mt-2 space-y-1.5 text-xs font-semibold text-[color:var(--fam-text)]"></ol>
                </div>
            </div>
        </div>

        <div id="chess-toast" class="hidden rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="text-xs font-semibold text-[color:var(--fam-text)]" id="chess-toast-msg"></div>
        </div>

        <div id="chess-promo" class="hidden fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40" data-close="1"></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center p-4">
                <div class="w-full sm:max-w-sm rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-xl p-4">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Promotion</div>
                    <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">Choisis la pièce</div>
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        <button type="button" class="chess-promo-btn rounded-xl border border-[color:var(--fam-border)] bg-white hover:bg-slate-50 px-3 py-3 text-xl" data-piece="q">♛</button>
                        <button type="button" class="chess-promo-btn rounded-xl border border-[color:var(--fam-border)] bg-white hover:bg-slate-50 px-3 py-3 text-xl" data-piece="r">♜</button>
                        <button type="button" class="chess-promo-btn rounded-xl border border-[color:var(--fam-border)] bg-white hover:bg-slate-50 px-3 py-3 text-xl" data-piece="b">♝</button>
                        <button type="button" class="chess-promo-btn rounded-xl border border-[color:var(--fam-border)] bg-white hover:bg-slate-50 px-3 py-3 text-xl" data-piece="n">♞</button>
                    </div>
                    <button type="button" class="mt-3 w-full text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50" data-close="1">Annuler</button>
                </div>
            </div>
        </div>

    </div>

</x-app-layout>
