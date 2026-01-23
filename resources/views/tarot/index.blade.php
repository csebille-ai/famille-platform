@php
    $draft = is_array($draft ?? null) ? $draft : null;
    $hasDraft = is_array($draft);
    $isActive = $hasDraft && (request()->query('view') === 'active');
    $spread = (string) ($draft['spread'] ?? 'three');

    /** @var \Illuminate\Support\Collection<int, \App\Models\TarotReading> $recentReadings */
    $recentReadings = $recentReadings ?? collect();

    $quickPrompts = [
        'Comment aborder sereinement la semaine à venir ?'
        , 'Quel est le bon prochain pas pour moi ?'
        , 'Qu’est-ce que je dois lâcher pour avancer ?'
    ];
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-3xl mx-auto px-6 pt-2 pb-[calc(5.5rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))] sm:pt-4 sm:pb-10 space-y-4">
        <div class="px-1">
            <div class="text-2xl font-extrabold tracking-tight text-[color:var(--fam-text)]">Tarot</div>
            <div class="mt-1 text-sm text-[color:var(--fam-muted)]">Tirage du jour (familial)</div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-[color:var(--fam-text)]">Nouveau tirage</h2>
                </div>

                @if($hasDraft)
                    <form method="POST" action="{{ route('tarot.reset') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex items-center h-9 px-3 rounded-xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-semibold text-[color:var(--fam-muted)] hover:bg-[color:var(--fam-primary-100)]">
                            Nouveau
                        </button>
                    </form>
                @endif
            </div>

            <form id="tarot-draw-form" method="POST" action="{{ route('tarot.draw') }}" class="mt-4 space-y-4" data-tarot-draw-form>
                @csrf

                <div>
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Type de tirage</div>
                    <div class="mt-2 flex items-center gap-2" role="group" aria-label="Type de tirage">
                        <label class="cursor-pointer">
                            <input type="radio" name="spread" value="three" class="sr-only peer" {{ old('spread', 'three') === 'three' ? 'checked' : '' }}>
                            <span class="inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold border transition
                                bg-[color:var(--fam-surface-2)] text-[color:var(--fam-muted)] border-[color:var(--fam-border-soft)]
                                peer-checked:bg-[color:var(--fam-primary-100)] peer-checked:text-[color:var(--fam-primary-hover)] peer-checked:border-[color:var(--fam-primary-200)]">
                                3 cartes
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="spread" value="five" class="sr-only peer" {{ old('spread') === 'five' ? 'checked' : '' }}>
                            <span class="inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold border transition
                                bg-[color:var(--fam-surface-2)] text-[color:var(--fam-muted)] border-[color:var(--fam-border-soft)]
                                peer-checked:bg-[color:var(--fam-primary-100)] peer-checked:text-[color:var(--fam-primary-hover)] peer-checked:border-[color:var(--fam-primary-200)]">
                                5 cartes
                            </span>
                        </label>
                    </div>
                    <div class="mt-1 text-xs text-[color:var(--fam-muted)]">3 pour aller droit au but, 5 pour décortiquer.</div>
                </div>

                <div>
                    <label for="question" class="block text-sm font-semibold text-[color:var(--fam-text)]">
                        Question
                        <span class="text-[color:var(--fam-muted)] font-medium">(facultatif)</span>
                    </label>
                    <textarea id="question" name="question" rows="3" class="mt-1 block w-full rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-4 py-3 text-sm text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary-200)] focus:ring-offset-2" placeholder="Ex: Comment aborder sereinement la semaine à venir ?">{{ old('question') }}</textarea>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" id="tarot-stt-toggle" class="inline-flex items-center gap-2 h-9 px-3 rounded-xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-semibold text-[color:var(--fam-muted)] hover:bg-[color:var(--fam-primary-100)]" aria-pressed="false">
                            <span class="relative inline-flex h-2 w-2">
                                <span id="tarot-stt-dot" class="hidden absolute inline-flex h-full w-full rounded-full bg-[color:var(--fam-primary)] opacity-75"></span>
                                <span id="tarot-stt-dot-ping" class="hidden absolute inline-flex h-full w-full rounded-full bg-[color:var(--fam-primary)] opacity-50 animate-ping"></span>
                            </span>
                            <span id="tarot-stt-label">Dicter</span>
                        </button>

                        <div id="tarot-stt-status" class="text-xs text-[color:var(--fam-muted)]"></div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($quickPrompts as $p)
                            <button type="button" class="inline-flex items-center h-8 px-3 rounded-full border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-2)] text-xs font-semibold text-[color:var(--fam-muted)] hover:bg-white" data-tarot-prompt="{{ $p }}">{{ $p }}</button>
                        @endforeach
                    </div>

                    <div class="mt-1 text-xs text-[color:var(--fam-muted)]">
                        Max 500 caractères. Besoin d’un point de départ ?
                        <button type="button" class="font-semibold text-[color:var(--fam-primary)] hover:text-[color:var(--fam-primary-hover)]" data-tarot-example>Remplir un exemple</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-40" data-tarot-sticky-bar>
            <div class="mx-auto max-w-3xl px-6">
                <div class="mb-[calc(0.75rem+env(safe-area-inset-bottom))] rounded-2xl border border-[color:var(--fam-border-soft)] bg-[color:rgba(255,255,255,0.70)] supports-[backdrop-filter]:bg-[color:rgba(255,255,255,0.55)] supports-[backdrop-filter]:backdrop-blur-xl shadow-[0_12px_28px_rgba(17,24,39,0.10)] p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Action</div>
                            <div class="mt-0.5 text-sm font-semibold text-[color:var(--fam-text)]" data-tarot-sticky-subtitle>Prêt pour un tirage</div>
                        </div>

                        <button type="submit" form="tarot-draw-form" class="inline-flex items-center justify-center h-11 px-5 rounded-2xl text-sm font-semibold text-white bg-[color:var(--fam-primary)] hover:bg-[color:var(--fam-primary-hover)] disabled:opacity-60 disabled:cursor-not-allowed" data-tarot-submit>
                            <span data-tarot-submit-label>Tirer 3 cartes</span>
                            <span class="hidden items-center gap-2" data-tarot-submit-loading>
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                                </svg>
                                <span>Tirage…</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4" data-tarot-stage>
            <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4 hidden" data-tarot-stage-skeleton aria-hidden="true">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="mt-1 text-base font-semibold text-gray-900">Tirage en cours…</div>
                        <div class="mt-1 text-xs text-slate-500">Je sélectionne les cartes et j’écris l’interprétation.</div>
                    </div>
                </div>

                <div class="h-10 rounded-xl bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                <div class="h-[300px] rounded-2xl bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                <div class="space-y-2">
                    <div class="h-4 w-2/3 rounded bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                    <div class="h-4 w-1/2 rounded bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                    <div class="h-4 w-5/6 rounded bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                    <div class="mt-2 text-xs text-slate-500">Interprétation en rédaction…</div>
                </div>
            </div>

            @if(!$hasDraft)
                <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white p-5 sm:p-6" data-tarot-stage-empty>
                    <div class="flex items-start gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-[color:var(--fam-primary-100)] border border-[color:var(--fam-primary-200)] flex items-center justify-center text-[color:var(--fam-primary-hover)] shrink-0">
                            <i class="ph ph-cards-three" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Aucun tirage pour l’instant</div>
                            <div class="mt-1 text-sm text-[color:var(--fam-muted)]">Choisis 3 ou 5 cartes, puis lance un tirage.</div>
                            <div class="mt-2 text-sm text-[color:var(--fam-muted)]">
                                Exemple :
                                <button type="button" class="font-semibold text-[color:var(--fam-primary)] hover:text-[color:var(--fam-primary-hover)]" data-tarot-example>« Comment aborder sereinement la semaine à venir ? »</button>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif(!$isActive)
                @php
                    $cards = (array) ($draft['cards'] ?? []);
                    $count = count($cards);
                    $generatedAt = (string) ($draft['generated_at'] ?? '');
                    $generatedLabel = '';
                    try {
                        if ($generatedAt !== '') {
                            $generatedLabel = \Carbon\Carbon::parse($generatedAt)->diffForHumans();
                        }
                    } catch (\Throwable $e) {
                        $generatedLabel = '';
                    }
                @endphp
                <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6" data-tarot-stage-last>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Dernier tirage</h2>
                            @if($generatedLabel !== '')
                                <div class="mt-0.5 text-xs text-slate-500">{{ $generatedLabel }}</div>
                            @endif
                        </div>
                        <a href="{{ route('tarot.index', ['view' => 'active']) }}#tarot-reading" class="text-sm font-semibold text-[color:var(--fam-primary)] hover:text-[color:var(--fam-primary-hover)]">Voir l’interprétation</a>
                    </div>

                    @if($count > 0)
                        <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                            @foreach(array_slice($cards, 0, 5) as $c)
                                @php
                                    $file = (string) ($c['file'] ?? '');
                                    if ($file !== '' && mb_strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) === 'webp') {
                                        $file = preg_replace('/\.[Ww][Ee][Bb][Pp]$/', '.png', $file) ?? $file;
                                    }
                                    $reversed = !empty($c['reversed']);
                                    $orientation = (string) ($c['orientation'] ?? ($reversed ? 'reversed' : 'upright'));
                                    $img = $file !== '' ? asset('tarot/' . ltrim($file, '/')) : '';
                                @endphp
                                <div class="rounded-xl bg-white shadow-[0_6px_16px_rgba(15,23,42,0.10)]">
                                    @include('tarot._card-frame', [
                                        'src' => $img,
                                        'alt' => '',
                                        'variant' => 'thumb',
                                        'class' => 'h-[118px] w-full rounded-xl bg-white',
                                        'imgClass' => 'bg-transparent',
                                        'loading' => 'lazy',
                                        'decoding' => 'async',
                                        'rotate' => ((((string) $orientation) === 'reversed' || $reversed) ? 180 : 0),
                                    ])
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            @else
                <div id="tarot-active" tabindex="-1" class="bg-white rounded-2xl shadow-sm p-4 sm:p-6 space-y-3" style="scroll-margin-top: 5.5rem;" data-tarot-stage-active>
                    <div class="inline-flex items-center rounded-2xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] p-0.5" role="tablist" aria-label="Affichage tarot">
                        <button type="button" class="h-8 px-3 rounded-2xl text-[13px] font-semibold transition focus-visible:outline-none focus-visible:shadow-[0_0_0_3px_rgba(14,165,160,0.18)]" data-tarot-tab="cards" role="tab">Cartes</button>
                        <button type="button" class="h-8 px-3 rounded-2xl text-[13px] font-semibold transition focus-visible:outline-none focus-visible:shadow-[0_0_0_3px_rgba(14,165,160,0.18)]" data-tarot-tab="reading" role="tab">Lecture</button>
                    </div>

                    <div data-tarot-panel="cards" class="pb-[calc(1.25rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))]">
                        @include('tarot._cards', [
                            'cards' => (array) ($draft['cards'] ?? []),
                            'spread' => $spread,
                            'idPrefix' => 'tarot-draft',
                        ])
                    </div>

                @php
                    $interpretationText = (string) ($draft['interpretation'] ?? '');

                    $extractInterpretation = function (string $payload): ?string {
                        $payload = trim($payload);
                        if ($payload === '') return null;

                        $jsonCandidate = $payload;
                        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $payload, $m) === 1) {
                            $jsonCandidate = $m[0];
                        }

                        $decoded = json_decode($jsonCandidate, true);
                        if (is_array($decoded) && isset($decoded['interpretation']) && is_string($decoded['interpretation'])) {
                            return $decoded['interpretation'];
                        }

                        // Fallback for truncated/invalid JSON where newlines might not be escaped.
                        if (preg_match('/"interpretation"\s*:\s*"(?<val>.*?)(?="\s*,\s*"spoken_text"|"\s*\})/s', $jsonCandidate, $mm) === 1) {
                            $raw = (string) ($mm['val'] ?? '');
                            $raw = str_replace(["\r\n", "\r", "\n"], "\\n", $raw);
                            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $raw);
                            $val = json_decode('"' . $escaped . '"');
                            if (is_string($val)) return $val;
                            return stripcslashes((string) ($mm['val'] ?? ''));
                        }

                        return null;
                    };

                    $maybeInterpretation = $extractInterpretation($interpretationText);
                    if (is_string($maybeInterpretation) && trim($maybeInterpretation) !== '') {
                        $interpretationText = $maybeInterpretation;
                    }

                    // Some stored payloads contain literal "\\n" sequences.
                    $interpretationText = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $interpretationText);
                    $interpretationText = str_replace("\r\n", "\n", $interpretationText);
                    $interpretationText = preg_replace('/^\s*✅\s+/mu', '- ', $interpretationText) ?? $interpretationText;
                    $interpretationText = preg_replace('/^(Passé|Présent|Futur|Défi|Conseil|Issue probable|Le conseil qui pique mais qui aide|Le twist final)\s*:/mu', '**$1 :**', $interpretationText) ?? $interpretationText;
                    $interpretationText = preg_replace('/^##\s*Annonce du tirage\s*\n+/mi', '', $interpretationText) ?? $interpretationText;
                    $interpretationText = trim($interpretationText);

                    $spokenText = (string) ($draft['spoken_text'] ?? '');
                    $ttsText = trim($spokenText) !== '' ? $spokenText : $interpretationText;
                @endphp

                    <div class="sr-only" id="tarot-tts-text">{{ $ttsText }}</div>

                    <div data-tarot-panel="reading" class="hidden pb-[calc(1.25rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))]">
                        <div class="flex items-center justify-end gap-3">
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-[color:var(--fam-muted)] select-none">
                                    <input type="checkbox" id="tarot-tts-toggle" class="sr-only peer" />
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-[color:var(--fam-surface-2)] border border-[color:var(--fam-border-soft)] transition-colors peer-checked:bg-[color:var(--fam-primary-100)]" aria-hidden="true">
                                        <span class="inline-block h-5 w-5 translate-x-1 rounded-full bg-white transition-transform" id="tarot-tts-toggle-dot"></span>
                                    </span>
                                    <span class="font-semibold text-[color:var(--fam-text)]">Audio</span>
                                </label>
                                <div id="tarot-tts-status" class="text-xs text-[color:var(--fam-muted)]"></div>
                            </div>
                        </div>

                    <div id="tarot-reading" class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 leading-relaxed [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:my-3 [&_ul]:pl-5 [&_ul]:list-disc [&_ol]:my-3 [&_ol]:pl-5 [&_ol]:list-decimal [&_li]:mb-1 [&_strong]:font-semibold">
                        {!! \Illuminate\Support\Str::markdown($interpretationText, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>

                    <form method="POST" action="{{ route('tarot.save') }}" class="flex items-center gap-3">
                        @csrf
                        <button type="submit" class="inline-flex items-center h-10 px-4 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-semibold text-[color:var(--fam-muted)] hover:bg-[color:var(--fam-primary-100)]">Enregistrer</button>
                        <div class="text-xs text-[color:var(--fam-muted)]">Stocké en privé dans ton historique.</div>
                    </form>
                </div>
            @endif
        </div>

        @if($recentReadings instanceof \Illuminate\Support\Collection && $recentReadings->count() > 0)
            <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Historique</div>
                        <div class="mt-0.5 text-xs text-[color:var(--fam-muted)]">Tes derniers tirages</div>
                    </div>
                    <a href="{{ route('tarot.history') }}" class="fam-link-subtle text-sm font-semibold">Voir tout</a>
                </div>

                <div class="mt-4 grid gap-3">
                    @foreach($recentReadings as $r)
                        @php
                            $rcards = is_array($r->cards ?? null) ? (array) $r->cards : [];
                            $first = (array) ($rcards[0] ?? []);
                            $file = trim((string) ($first['file'] ?? ''));
                            if ($file !== '' && mb_strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) === 'webp') {
                                $file = preg_replace('/\.[Ww][Ee][Bb][Pp]$/', '.png', $file) ?? $file;
                            }
                            $img = $file !== '' ? asset('tarot/' . ltrim($file, '/')) : '';
                            $spreadLabel = ((string) ($r->spread ?? 'three')) === 'five' ? '5 cartes' : '3 cartes';
                            $q = trim((string) ($r->question ?? ''));
                        @endphp

                        <a href="{{ route('tarot.history.show', $r) }}" class="group rounded-2xl border border-[color:var(--fam-border-soft)] bg-white p-3 hover:bg-[color:var(--fam-primary-100)] transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-9 rounded-xl bg-[color:var(--fam-surface-2)] border border-[color:var(--fam-border-soft)] overflow-hidden shrink-0">
                                    @if($img !== '')
                                        @include('tarot._card-frame', [
                                            'src' => $img,
                                            'alt' => '',
                                            'variant' => 'thumb',
                                            'class' => 'h-12 w-9 rounded-xl bg-white',
                                            'imgClass' => 'bg-transparent',
                                            'loading' => 'lazy',
                                            'decoding' => 'async',
                                            'rotate' => 0,
                                        ])
                                    @else
                                        <div class="h-full w-full flex items-center justify-center text-[color:var(--fam-muted)]">
                                            <i class="ph ph-star" aria-hidden="true"></i>
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $spreadLabel }}</div>
                                        <div class="text-xs text-[color:var(--fam-muted)] shrink-0">{{ optional($r->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
                                    </div>
                                    @if($q !== '')
                                        <div class="mt-1 text-xs text-[color:var(--fam-muted)] line-clamp-2">{{ $q }}</div>
                                    @else
                                        <div class="mt-1 text-xs text-[color:var(--fam-muted)]">Sans question</div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

<script>
(() => {
    // Anchor landing: after a draw redirect we arrive on #tarot-active.
    // Ensure the block is aligned (and not hidden under sticky headers).
    if ((window.location.hash || '').toLowerCase() === '#tarot-active') {
        window.addEventListener('load', () => {
            const el = document.getElementById('tarot-active');
            if (!el) return;
            try { el.scrollIntoView({ behavior: 'auto', block: 'start' }); } catch (e) {}
            try { el.focus({ preventScroll: true }); } catch (e) {}
        }, { once: true, passive: true });
    }

    // Tabs (Cartes / Lecture) — active only.
    const stageEl = document.querySelector('[data-tarot-stage-active]');
    const tabButtons = Array.from(document.querySelectorAll('[data-tarot-tab]'));
    const cardsPanel = document.querySelector('[data-tarot-panel="cards"]');
    const readingPanel = document.querySelector('[data-tarot-panel="reading"]');

    const TAB_STORAGE_KEY = 'tarot.tab';
    const applyTabUi = (tab) => {
        const isCards = tab === 'cards';
        if (cardsPanel) cardsPanel.classList.toggle('hidden', !isCards);
        if (readingPanel) readingPanel.classList.toggle('hidden', isCards);

        const stickyGoReading = document.querySelector('[data-tarot-sticky-bar] [data-tarot-go-reading]');
        const stickyGoCards = document.querySelector('[data-tarot-sticky-bar] [data-tarot-go-cards]');
        if (stickyGoReading) stickyGoReading.classList.toggle('hidden', !isCards);
        if (stickyGoCards) stickyGoCards.classList.toggle('hidden', isCards);

        tabButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-tarot-tab') === tab;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.setAttribute('tabindex', isActive ? '0' : '-1');
            btn.classList.toggle('bg-white', isActive);
            btn.classList.toggle('shadow-sm', isActive);
            btn.classList.toggle('text-slate-900', isActive);
            btn.classList.toggle('text-slate-600', !isActive);
            btn.classList.toggle('hover:text-slate-900', !isActive);
        });
    };

    const setTab = (tab, { persist = true } = {}) => {
        if (tab !== 'cards' && tab !== 'reading') tab = 'cards';
        applyTabUi(tab);
        if (persist) {
            try { localStorage.setItem(TAB_STORAGE_KEY, tab); } catch (e) {}
        }
        try {
            window.dispatchEvent(new CustomEvent('tarot:tab', { detail: { tab } }));
        } catch (e) {}
    };

    if (stageEl && tabButtons.length) {
        tabButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                setTab(btn.getAttribute('data-tarot-tab') || 'cards');
            });
        });

        const hash = (window.location.hash || '').toLowerCase();
        if (hash === '#tarot-reading' || hash === '#tarot-reading/'.toLowerCase()) {
            setTab('reading', { persist: false });
        } else if (hash === '#tarot-cards' || hash === '#tarot-cards/'.toLowerCase()) {
            setTab('cards', { persist: false });
        } else {
            let initial = 'cards';
            try {
                const stored = localStorage.getItem(TAB_STORAGE_KEY);
                if (stored === 'cards' || stored === 'reading') initial = stored;
            } catch (e) {}
            setTab(initial, { persist: false });
        }

        const goReadingBtn = document.querySelector('[data-tarot-go-reading]');
        const goCardsBtn = document.querySelector('[data-tarot-go-cards]');
        goReadingBtn?.addEventListener('click', () => {
            setTab('reading');
            setTimeout(() => document.getElementById('tarot-reading')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
        });
        goCardsBtn?.addEventListener('click', () => {
            setTab('cards');
            setTimeout(() => document.getElementById('tarot-cards')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0);
        });
    }

    // Submit loading state for draw
    const drawForm = document.querySelector('[data-tarot-draw-form]');
    const submitBtn = document.querySelector('[data-tarot-submit]');
    const submitLabel = document.querySelector('[data-tarot-submit-label]');
    const submitLoading = document.querySelector('[data-tarot-submit-loading]');
    const skeletonEl = document.querySelector('[data-tarot-stage-skeleton]');
    const stageActiveEl = document.querySelector('[data-tarot-stage-active]');
    const stageEmptyEl = document.querySelector('[data-tarot-stage-empty]');
    const stageLastEl = document.querySelector('[data-tarot-stage-last]');

    const updateSubmitLabel = () => {
        if (!drawForm || !submitLabel) return;
        const selected = drawForm.querySelector('input[name="spread"]:checked');
        const v = (selected && selected.value) ? String(selected.value) : 'three';
        submitLabel.textContent = (v === 'five') ? 'Tirer 5 cartes' : 'Tirer 3 cartes';
    };

    if (drawForm) {
        drawForm.addEventListener('change', (e) => {
            const t = e.target;
            if (!t) return;
            if (t.matches && t.matches('input[name="spread"]')) {
                updateSubmitLabel();
            }
        }, { passive: true });

        updateSubmitLabel();
    }

    drawForm?.addEventListener('submit', () => {
        try { drawForm.setAttribute('aria-busy', 'true'); } catch (e) {}
        try { submitBtn?.setAttribute('aria-disabled', 'true'); } catch (e) {}

        if (submitBtn) submitBtn.disabled = true;
        if (submitLabel) submitLabel.classList.add('hidden');
        if (submitLoading) {
            submitLoading.classList.remove('hidden');
            submitLoading.classList.add('inline-flex');
        }

        // Do NOT disable inputs/textarea: disabled fields are not submitted.
        // We only disable buttons to prevent double-submit while keeping values intact.
        drawForm.querySelectorAll('button').forEach((el) => {
            el.disabled = true;
        });

        if (skeletonEl) skeletonEl.classList.remove('hidden');
        if (stageActiveEl) stageActiveEl.classList.add('hidden');
        if (stageEmptyEl) stageEmptyEl.classList.add('hidden');
        if (stageLastEl) stageLastEl.classList.add('hidden');
    }, { passive: true });

    // Speech-to-text (dictation toggle)
    const sttToggleBtn = document.getElementById('tarot-stt-toggle');
    const sttDot = document.getElementById('tarot-stt-dot');
    const sttDotPing = document.getElementById('tarot-stt-dot-ping');
    const sttLabelEl = document.getElementById('tarot-stt-label');
    const sttStatusEl = document.getElementById('tarot-stt-status');
    const questionEl = document.getElementById('question');
    if (questionEl) {
        // No auto-focus: keep landing calm.
    }

    const setSttStatus = (msg) => {
        if (!sttStatusEl) return;
        sttStatusEl.textContent = msg || '';
    };

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognizer = null;
    let sttActive = false;

    const setSttUi = ({ active, supported }) => {
        if (sttToggleBtn) {
            sttToggleBtn.disabled = !supported;
            sttToggleBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
        if (sttDot) sttDot.classList.toggle('hidden', !active);
        if (sttDotPing) sttDotPing.classList.toggle('hidden', !active);
        if (sttLabelEl) sttLabelEl.textContent = active ? 'En écoute…' : 'Dicter';
    };

    if (sttToggleBtn && questionEl && SpeechRecognition) {
        recognizer = new SpeechRecognition();
        recognizer.lang = 'fr-FR';
        recognizer.interimResults = true;
        recognizer.continuous = true;

        let dictationBase = '';
        let dictationInterim = '';

        recognizer.onstart = () => {
            sttActive = true;
            dictationBase = (questionEl.value || '').trim();
            dictationInterim = '';
            setSttUi({ active: true, supported: true });
            setSttStatus('');
        };

        recognizer.onend = () => {
            sttActive = false;
            dictationInterim = '';
            setSttUi({ active: false, supported: true });
            setSttStatus('');
        };

        recognizer.onerror = () => {
            // Usually: not-allowed / no-speech / network
            sttActive = false;
            dictationInterim = '';
            setSttUi({ active: false, supported: true });
            setSttStatus('Dictée indisponible.');
        };

        recognizer.onresult = (event) => {
            let interimText = '';
            let finalText = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const res = event.results[i];
                const chunk = (res[0]?.transcript || '').trim();
                if (!chunk) continue;
                if (res.isFinal) {
                    finalText += (finalText ? ' ' : '') + chunk;
                } else {
                    interimText += (interimText ? ' ' : '') + chunk;
                }
            }

            if (finalText) {
                dictationBase = (dictationBase || '').trim();
                dictationBase = dictationBase ? (dictationBase + ' ' + finalText).trim() : finalText;
            }
            dictationInterim = interimText;

            const composed = [dictationBase, dictationInterim].filter(Boolean).join(' ').trim();
            questionEl.value = composed;
        };

        sttToggleBtn.addEventListener('click', () => {
            if (!recognizer) return;
            if (!sttActive) {
                try {
                    recognizer.start();
                } catch (e) {
                    setSttStatus('Dictée indisponible.');
                }
                return;
            }

            try { recognizer.stop(); } catch (e) {}
        });

        setSttUi({ active: false, supported: true });
    } else {
        setSttUi({ active: false, supported: false });
        setSttStatus('Dictée non supportée sur ce navigateur.');
    }

    // Quick prompts
    const fillQuestion = (text) => {
        if (!questionEl) return;
        questionEl.value = String(text || '');
        try { questionEl.focus({ preventScroll: true }); } catch (e) {}
    };

    document.querySelectorAll('[data-tarot-prompt]').forEach((btn) => {
        btn.addEventListener('click', () => fillQuestion(btn.getAttribute('data-tarot-prompt') || ''));
    });

    document.querySelectorAll('[data-tarot-example]').forEach((btn) => {
        btn.addEventListener('click', () => fillQuestion('Comment aborder sereinement la semaine à venir ?'));
    });

    const toggleEl = document.getElementById('tarot-tts-toggle');
    const dotEl = document.getElementById('tarot-tts-toggle-dot');
    const statusEl = document.getElementById('tarot-tts-status');
    const textEl = document.getElementById('tarot-tts-text');

    if (!toggleEl || !textEl) {
        return;
    }

    const STORAGE_KEY = 'tarot.tts.auto';

    const syncUi = () => {
        const on = !!toggleEl.checked;
        if (dotEl) {
            dotEl.style.transform = on ? 'translateX(1.25rem)' : 'translateX(0.25rem)';
        }
    };

    let openAiAudio = null;
    let pendingAutoplayRetry = false;
    let pendingHandler = null;

    const setStatus = (msg) => {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
    };

    const stopOpenAi = () => {
        if (openAiAudio) {
            try { openAiAudio.pause(); } catch (e) {}
            openAiAudio = null;
        }
        pendingAutoplayRetry = false;
        if (pendingHandler) {
            try { window.removeEventListener('pointerdown', pendingHandler); } catch (e) {}
            pendingHandler = null;
        }
        setStatus('');
    };

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const speakWithOpenAi = async () => {
        if (openAiAudio) {
            return;
        }

        const text = (textEl.textContent || '').trim();
        if (!text) {
            setStatus('Rien à lire.');
            return;
        }

        setStatus('Génération audio…');

        try {
            const resp = await fetch('{{ route('tarot.tts') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ text }),
            });

            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }

            const data = await resp.json();
            if (!data?.ok || !data?.url) {
                throw new Error('Réponse invalide');
            }

            openAiAudio = new Audio(data.url);
            openAiAudio.onended = () => setStatus('');
            try {
                await openAiAudio.play();
                setStatus('Lecture…');
                pendingAutoplayRetry = false;
            } catch (e) {
                pendingAutoplayRetry = true;
                setStatus('Auto-lecture bloquée. Tape une fois sur l’écran.');

                if (!pendingHandler) {
                    pendingHandler = async () => {
                        if (!toggleEl.checked) return;
                        if (!pendingAutoplayRetry) return;
                        pendingAutoplayRetry = false;
                        try { await speakWithOpenAi(); } catch (err) {}
                    };
                    window.addEventListener('pointerdown', pendingHandler, { once: true });
                }
            }
        } catch (e) {
            setStatus('OpenAI TTS indisponible.');
        }
    };

    // Load preference.
    try {
        toggleEl.checked = localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
        // ignore
    }
    syncUi();

    const setEnabled = async (enabled) => {
        try {
            localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        } catch (e) {
            // ignore
        }

        if (!enabled) {
            stopOpenAi();
            return;
        }

        await speakWithOpenAi();
    };

    toggleEl.addEventListener('change', () => {
        syncUi();
        setEnabled(!!toggleEl.checked).catch(() => {});
    });

    const isReadingTabActive = () => {
        const readingBtn = document.querySelector('[data-tarot-tab="reading"]');
        return readingBtn?.getAttribute('aria-selected') === 'true';
    };

    const maybeAutoplay = () => {
        if (!toggleEl.checked) return;
        if (!isReadingTabActive()) return;
        setTimeout(() => {
            speakWithOpenAi().catch(() => {});
        }, 0);
    };

    // Auto-start only when the Lecture tab is active.
    maybeAutoplay();

    window.addEventListener('tarot:tab', (e) => {
        const tab = e?.detail?.tab;
        if (tab === 'reading') {
            maybeAutoplay();
        } else {
            stopOpenAi();
        }
    });
})();
</script>
