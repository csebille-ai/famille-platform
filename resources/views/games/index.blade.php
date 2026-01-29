<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4">
        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Games</div>
            <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Jeux asynchrones en équipe</div>
        </div>

        <a href="{{ route('games.chess.index') }}" class="block rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                    <i class="ph ph-chess-rook text-[20px]" aria-hidden="true"></i>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Échecs (V1)</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Une partie en cours — joue quand c’est le tour de ton équipe</div>
                </div>

                <div class="text-[color:var(--fam-muted)] mt-1"><i class="ph ph-caret-right" aria-hidden="true"></i></div>
            </div>
        </a>

        <a href="{{ route('games.sliding-puzzles.index') }}" class="block rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                    <i class="ph ph-squares-four text-[20px]" aria-hidden="true"></i>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Taquin</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Puzzle à tuiles glissantes — temps + coups</div>
                </div>

                <div class="text-[color:var(--fam-muted)] mt-1"><i class="ph ph-caret-right" aria-hidden="true"></i></div>
            </div>
        </a>

        <a href="{{ route('quiz.index') }}" class="block rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                    <i class="ph ph-brain text-[20px]" aria-hidden="true"></i>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Quiz</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Questions générées depuis Wikidata — teste tes connaissances</div>
                </div>

                <div class="text-[color:var(--fam-muted)] mt-1"><i class="ph ph-caret-right" aria-hidden="true"></i></div>
            </div>
        </a>
    </div>
</x-app-layout>
