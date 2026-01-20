@php
    $spreadLabel = fn (string $s) => $s === 'five' ? '5 cartes' : '3 cartes';
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-3xl mx-auto px-6 py-6 space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tirage Tarot</h1>
                <div class="text-sm text-slate-500 mt-1">
                    {{ $spreadLabel((string) $reading->spread) }}
                    <span class="text-slate-400">·</span>
                    {{ $reading->created_at?->diffForHumans() }}
                </div>
            </div>
            <a href="{{ route('tarot.history') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Retour historique</a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4" data-tarot-stage>
            <div>
                <div class="text-sm text-slate-500">Question</div>
                <div class="mt-1 text-base font-semibold text-gray-900">{{ $reading->question }}</div>
            </div>

            <div class="inline-flex items-center rounded-2xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] p-1" role="tablist" aria-label="Affichage tarot">
                <button type="button" class="h-9 px-4 rounded-2xl text-sm font-semibold transition" data-tarot-tab="cards" role="tab">Cartes</button>
                <button type="button" class="h-9 px-4 rounded-2xl text-sm font-semibold transition" data-tarot-tab="reading" role="tab">Lecture</button>
            </div>

            <div data-tarot-panel="cards">
                @include('tarot._cards', [
                    'cards' => (array) ($reading->cards ?? []),
                    'spread' => (string) $reading->spread,
                    'idPrefix' => 'tarot-reading',
                ])

                <div class="flex items-center justify-end">
                    <button type="button" class="text-sm font-semibold text-[color:var(--fam-primary)] hover:text-[color:var(--fam-primary-hover)]" data-tarot-go-reading>
                        Lire l’interprétation
                    </button>
                </div>
            </div>

            @php
                $interpretationText = (string) $reading->interpretation;

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
                $interpretationText = preg_replace('/^(Passé|Présent|Futur|Le conseil qui pique mais qui aide|Le twist final)\s*:/mu', '**$1 :**', $interpretationText) ?? $interpretationText;
                $interpretationText = preg_replace('/^##\s*Annonce du tirage\s*\n+/mi', '', $interpretationText) ?? $interpretationText;
                $interpretationText = trim($interpretationText);

                $spokenText = (string) ($reading->spoken_text ?? '');
                $ttsText = trim($spokenText) !== '' ? $spokenText : $interpretationText;
            @endphp

            <div class="sr-only" id="tarot-tts-text">{{ $ttsText }}</div>

            <div data-tarot-panel="reading" class="hidden">
                <div class="flex items-center justify-between gap-3">
                    <button type="button" class="text-sm font-semibold text-slate-600 hover:text-slate-800" data-tarot-go-cards>
                        Voir les cartes
                    </button>

                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 select-none">
                            <input type="checkbox" id="tarot-tts-toggle" class="sr-only peer" />
                            <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 transition-colors peer-checked:bg-indigo-600" aria-hidden="true">
                                <span class="inline-block h-5 w-5 translate-x-1 rounded-full bg-white transition-transform" id="tarot-tts-toggle-dot"></span>
                            </span>
                            <span class="font-semibold">Audio</span>
                        </label>
                        <div id="tarot-tts-status" class="text-xs text-slate-500"></div>
                    </div>
                </div>

                <div id="tarot-reading" class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 leading-relaxed [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:my-3 [&_ul]:pl-5 [&_ul]:list-disc [&_ol]:my-3 [&_ol]:pl-5 [&_ol]:list-decimal [&_li]:mb-1 [&_strong]:font-semibold">
                    {!! \Illuminate\Support\Str::markdown($interpretationText, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('tarot.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Nouveau tirage</a>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
(() => {
    // Tabs (Cartes / Lecture)
    const resultEl = document.querySelector('[data-tarot-stage]');
    const tabButtons = Array.from(document.querySelectorAll('[data-tarot-tab]'));
    const cardsPanel = document.querySelector('[data-tarot-panel="cards"]');
    const readingPanel = document.querySelector('[data-tarot-panel="reading"]');

    const TAB_STORAGE_KEY = 'tarot.tab';
    const applyTabUi = (tab) => {
        const isCards = tab === 'cards';
        if (cardsPanel) cardsPanel.classList.toggle('hidden', !isCards);
        if (readingPanel) readingPanel.classList.toggle('hidden', isCards);

        tabButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-tarot-tab') === tab;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.setAttribute('tabindex', isActive ? '0' : '-1');
            btn.classList.toggle('bg-white', isActive);
            btn.classList.toggle('shadow-sm', isActive);
            btn.classList.toggle('text-slate-900', isActive);
            btn.classList.toggle('text-slate-600', !isActive);
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

    if (resultEl && tabButtons.length) {
        tabButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                setTab(btn.getAttribute('data-tarot-tab') || 'cards');
            });
        });

        const hash = (window.location.hash || '').toLowerCase();
        if (hash === '#tarot-reading') {
            setTab('reading', { persist: false });
        } else if (hash === '#tarot-cards') {
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

    const toggleEl = document.getElementById('tarot-tts-toggle');
    const dotEl = document.getElementById('tarot-tts-toggle-dot');
    const statusEl = document.getElementById('tarot-tts-status');
    const textEl = document.getElementById('tarot-tts-text');

    if (!toggleEl || !textEl) return;

    const STORAGE_KEY = 'tarot.tts.auto';

    const syncUi = () => {
        const on = !!toggleEl.checked;
        if (dotEl) {
            dotEl.style.transform = on ? 'translateX(1.25rem)' : 'translateX(0.25rem)';
        }
    };

    const setStatus = (msg) => {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
    };

    let openAiAudio = null;
    let pendingAutoplayRetry = false;
    let pendingHandler = null;

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
                // Autoplay is often blocked unless triggered by a user gesture.
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
        } finally {
            // no-op
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

        // Start immediately when user enables.
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
