@php
    $spreadLabel = fn (string $s) => $s === 'five' ? '5 cartes' : '3 cartes';
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-3xl mx-auto px-6 pt-3 pb-6 sm:py-6 space-y-6">
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

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('tarot.draw') }}" class="space-y-4" data-tarot-draw-form>
                @csrf

                <div>
                    <label for="question" class="block text-sm font-semibold text-gray-900">Ta question</label>
                    <input type="hidden" name="question" value="{{ old('question') }}" data-question-mirror>
                    <textarea id="question" name="question" rows="3" autofocus class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" placeholder="Ex: Comment aborder sereinement la semaine à venir ?">{{ old('question') }}</textarea>
                    <div class="microcopy mt-1 text-xs text-slate-500">Max 500 caractères.</div>

                    <div class="mt-2 flex items-center gap-3">
                        <x-secondary-button type="button" id="tarot-stt-start">Dicter</x-secondary-button>
                        <x-secondary-button type="button" id="tarot-stt-stop">Stop</x-secondary-button>
                        <div id="tarot-stt-status" class="text-xs text-slate-500"></div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-sm font-semibold text-gray-900">Tirage</div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="spread" value="three" class="rounded" {{ old('spread', 'three') === 'three' ? 'checked' : '' }}>
                        <span>3 cartes</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="spread" value="five" class="rounded" {{ old('spread') === 'five' ? 'checked' : '' }}>
                        <span>5 cartes (croix)</span>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button data-tarot-submit>
                        <span data-tarot-submit-label>Tirer</span>
                        <span class="hidden items-center gap-2" data-tarot-submit-loading>
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                            </svg>
                            <span>Tirage en cours…</span>
                        </span>
                    </x-primary-button>

                    <a href="{{ route('tarot.history') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir l’historique</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4 hidden" data-tarot-result-skeleton aria-hidden="true">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm text-slate-500">Résultat</div>
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

        @if (is_array($draft ?? null))
            <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4" data-tarot-result>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-sm text-slate-500">Résultat ({{ $spreadLabel((string) ($draft['spread'] ?? 'one')) }})</div>
                        <div class="mt-1 text-base font-semibold text-gray-900 truncate">{{ (string) ($draft['question'] ?? '') }}</div>
                    </div>
                    <form method="POST" action="{{ route('tarot.reset') }}" class="shrink-0">
                        @csrf
                        <x-secondary-button type="submit">Relancer</x-secondary-button>
                    </form>
                </div>

                <div class="inline-flex items-center rounded-2xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] p-1" role="tablist" aria-label="Affichage du résultat">
                    <button type="button" class="h-9 px-4 rounded-2xl text-sm font-semibold transition focus-visible:outline-none focus-visible:shadow-[0_0_0_3px_rgba(14,165,160,0.18)]" data-tarot-tab="cards" role="tab">Cartes</button>
                    <button type="button" class="h-9 px-4 rounded-2xl text-sm font-semibold transition focus-visible:outline-none focus-visible:shadow-[0_0_0_3px_rgba(14,165,160,0.18)]" data-tarot-tab="reading" role="tab">Lecture</button>
                </div>

                @php
                    $spread = (string) ($draft['spread'] ?? 'three');
                @endphp

                <div data-tarot-panel="cards" class="pb-[calc(5.75rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))]">
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
                    $interpretationText = preg_replace('/^(Passé|Présent|Futur|Le conseil qui pique mais qui aide|Le twist final)\s*:/mu', '**$1 :**', $interpretationText) ?? $interpretationText;
                    $interpretationText = preg_replace('/^##\s*Annonce du tirage\s*\n+/mi', '', $interpretationText) ?? $interpretationText;
                    $interpretationText = trim($interpretationText);

                    $spokenText = (string) ($draft['spoken_text'] ?? '');
                    $ttsText = trim($spokenText) !== '' ? $spokenText : $interpretationText;
                @endphp

                <div class="sr-only" id="tarot-tts-text">{{ $ttsText }}</div>

                <div data-tarot-panel="reading" class="hidden pb-[calc(5.75rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))]">
                    <div class="flex items-center justify-end gap-3">
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

                <div
                    class="sticky z-[60] rounded-2xl border border-[color:var(--fam-border-soft)] bg-white/95 supports-[backdrop-filter]:bg-white/80 supports-[backdrop-filter]:backdrop-blur-xl shadow-[0_-16px_40px_rgba(15,23,42,0.18)]"
                    style="bottom: calc(var(--mobile-bottom-nav-h,4rem) + env(safe-area-inset-bottom) + 0.75rem);"
                    data-tarot-sticky-bar
                    aria-label="Actions du résultat tarot"
                >
                    <div class="px-4 py-3 flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold text-slate-500 whitespace-nowrap" data-card-reminder></div>
                            <div class="mt-0.5 flex items-center gap-2 min-w-0">
                                <div class="text-sm font-semibold text-slate-900 truncate" data-active-name></div>
                                <span class="hidden fam-chip text-xs" data-active-reversed>Renversée</span>
                            </div>
                        </div>
                        <button type="button" class="hidden text-sm font-semibold text-slate-700 hover:text-slate-900" data-tarot-go-cards>
                            Retour aux cartes
                        </button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl bg-[color:var(--fam-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[color:var(--fam-primary-hover)] focus-visible:outline-none focus-visible:shadow-[0_0_0_3px_rgba(14,165,160,0.28)]" data-tarot-go-reading>
                            Lire l’interprétation
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('tarot.save') }}" class="flex items-center gap-3">
                    @csrf
                    <x-primary-button type="submit">Enregistrer</x-primary-button>
                    <div class="text-xs text-slate-500">Stocké en privé dans ton historique.</div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>

<script>
(() => {
    // Result tabs (Cartes / Lecture)
    const resultEl = document.querySelector('[data-tarot-result]');
    const tabButtons = Array.from(document.querySelectorAll('[data-tarot-tab]'));
    const cardsPanel = document.querySelector('[data-tarot-panel="cards"]');
    const readingPanel = document.querySelector('[data-tarot-panel="reading"]');

    const TAB_STORAGE_KEY = 'tarot.result.tab';
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

    if (resultEl && tabButtons.length) {
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
    const skeletonEl = document.querySelector('[data-tarot-result-skeleton]');

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
        if (resultEl) resultEl.classList.add('hidden');
    }, { passive: true });

    // Speech-to-text (mobile dictation)
    const sttStartBtn = document.getElementById('tarot-stt-start');
    const sttStopBtn = document.getElementById('tarot-stt-stop');
    const sttStatusEl = document.getElementById('tarot-stt-status');
    const questionEl = document.getElementById('question');
    const questionMirrorEl = document.querySelector('[data-question-mirror]');

    const syncQuestionMirror = () => {
        if (!questionEl || !questionMirrorEl) return;
        questionMirrorEl.value = questionEl.value || '';
    };

    if (questionEl) {
        // Keep mirror updated as user types, so submit works even if the textarea is disabled.
        questionEl.addEventListener('input', syncQuestionMirror, { passive: true });
        syncQuestionMirror();

        // Auto-focus the question field when opening Tarot.
        // Use a small delay to avoid layout/scroll jumps on mobile.
        setTimeout(() => {
            try {
                questionEl.focus({ preventScroll: true });
            } catch (e) {
                try { questionEl.focus(); } catch (e2) {}
            }
        }, 60);
    }

    const setSttStatus = (msg) => {
        if (!sttStatusEl) return;
        sttStatusEl.textContent = msg || '';
    };

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognizer = null;
    let sttActive = false;

    if (sttStartBtn && sttStopBtn && questionEl && SpeechRecognition) {
        recognizer = new SpeechRecognition();
        recognizer.lang = 'fr-FR';
        recognizer.interimResults = true;
        recognizer.continuous = false;

        let baseText = '';

        recognizer.onstart = () => {
            sttActive = true;
            sttStartBtn.disabled = true;
            sttStopBtn.disabled = false;
            baseText = (questionEl.value || '').trim();
            setSttStatus('J’écoute…');
        };

        recognizer.onend = () => {
            sttActive = false;
            sttStartBtn.disabled = false;
            sttStopBtn.disabled = true;
            setSttStatus('');
        };

        recognizer.onerror = () => {
            // Usually: not-allowed / no-speech / network
            sttActive = false;
            sttStartBtn.disabled = false;
            sttStopBtn.disabled = true;
            setSttStatus('Dictée indisponible.');
        };

        recognizer.onresult = (event) => {
            let interim = '';
            let finalText = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const res = event.results[i];
                const chunk = (res[0]?.transcript || '').trim();
                if (!chunk) continue;
                if (res.isFinal) {
                    finalText += (finalText ? ' ' : '') + chunk;
                } else {
                    interim += (interim ? ' ' : '') + chunk;
                }
            }

            const combined = [baseText, finalText || interim].filter(Boolean).join(baseText ? ' ' : '');
            questionEl.value = combined;
            syncQuestionMirror();
        };

        sttStopBtn.disabled = true;

        sttStartBtn.addEventListener('click', () => {
            if (sttActive) return;
            try {
                // iOS/Safari may not support; Android Chrome does.
                recognizer.start();
            } catch (e) {
                setSttStatus('Dictée indisponible.');
            }
        });

        sttStopBtn.addEventListener('click', () => {
            try {
                recognizer.stop();
            } catch (e) {}
        });
    } else {
        if (sttStartBtn) sttStartBtn.disabled = true;
        if (sttStopBtn) sttStopBtn.disabled = true;
        if (sttStatusEl) setSttStatus('Dictée non supportée sur ce navigateur.');
    }

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
