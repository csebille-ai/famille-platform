@php
    $spreadLabel = fn (string $s) => $s === 'five' ? '5 cartes' : '3 cartes';
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-3xl mx-auto px-6 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tarot</h1>
            <div class="text-sm text-slate-500 mt-1">Tirage fun et bienveillant (aide à la réflexion).</div>
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

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('tarot.draw') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="question" class="block text-sm font-semibold text-gray-900">Ta question</label>
                    <textarea id="question" name="question" rows="3" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" placeholder="Ex: Comment aborder sereinement la semaine à venir ?">{{ old('question') }}</textarea>
                    <div class="mt-1 text-xs text-slate-500">Max 500 caractères.</div>

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
                    <x-primary-button>
                        Tirer
                    </x-primary-button>

                    <a href="{{ route('tarot.history') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir l’historique</a>
                </div>
            </form>
        </div>

        @if (is_array($draft ?? null))
            <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
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

                @php
                    $spread = (string) ($draft['spread'] ?? 'three');
                @endphp

                @if ($spread === 'five')
                    @php
                        $pos = [
                            ['row' => 1, 'col' => 2], // top
                            ['row' => 2, 'col' => 1], // left
                            ['row' => 2, 'col' => 2], // center
                            ['row' => 2, 'col' => 3], // right
                            ['row' => 3, 'col' => 2], // bottom
                        ];
                    @endphp

                    <div class="grid grid-cols-3 gap-3">
                        @foreach ((array) ($draft['cards'] ?? []) as $i => $c)
                            @php
                                $p = $pos[(int) $i] ?? ['row' => 1, 'col' => 1];
                            @endphp
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4" style="grid-row: {{ $p['row'] }}; grid-column: {{ $p['col'] }};">
                                @if (!empty($c['file']))
                                    <div class="flex justify-center">
                                        <img
                                            src="{{ 'https://opanoma.fr/tarot/' . $c['file'] }}"
                                            alt="{{ $c['name'] ?? '' }}"
                                            class="h-56 w-auto max-w-full rounded-lg border border-slate-200 bg-white object-contain"
                                            style="transform: {{ !empty($c['reversed']) ? 'rotate(180deg)' : 'none' }};"
                                            loading="lazy"
                                        />
                                    </div>
                                @endif
                                <div class="mt-3 text-sm font-semibold text-gray-900">{{ $c['name'] ?? '' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $c['keywords'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ((array) ($draft['cards'] ?? []) as $c)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                            @if (!empty($c['file']))
                                <div class="flex justify-center">
                                    <img
                                        src="{{ 'https://opanoma.fr/tarot/' . $c['file'] }}"
                                        alt="{{ $c['name'] ?? '' }}"
                                        class="h-56 w-auto max-w-full rounded-lg border border-slate-200 bg-white object-contain"
                                        style="transform: {{ !empty($c['reversed']) ? 'rotate(180deg)' : 'none' }};"
                                        loading="lazy"
                                    />
                                </div>
                            @endif
                            <div class="mt-3 text-sm font-semibold text-gray-900">{{ $c['name'] ?? '' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $c['keywords'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center gap-3">
                    <x-secondary-button type="button" id="tarot-tts-openai">Audio (OpenAI)</x-secondary-button>
                    <div id="tarot-tts-status" class="text-xs text-slate-500"></div>
                </div>

                <div class="sr-only" id="tarot-tts-text">{{ trim((string) ($draft['spoken_text'] ?? '')) !== '' ? (string) ($draft['spoken_text'] ?? '') : (string) ($draft['interpretation'] ?? '') }}</div>

                @php
                    $interpretationText = (string) ($draft['interpretation'] ?? '');
                    $maybeJson = trim($interpretationText);
                    if ($maybeJson !== '' && str_starts_with($maybeJson, '{')) {
                        $decoded = json_decode($maybeJson, true);
                        if (!is_array($decoded) && preg_match('/\{(?:[^{}]|(?R))*\}/s', $maybeJson, $m) === 1) {
                            $decoded = json_decode($m[0], true);
                        }
                        if (is_array($decoded) && isset($decoded['interpretation'])) {
                            $interpretationText = (string) $decoded['interpretation'];
                        }
                    }

                    $interpretationText = str_replace("\r\n", "\n", $interpretationText);
                    $interpretationText = preg_replace('/^\s*✅\s+/mu', '- ', $interpretationText) ?? $interpretationText;
                    $interpretationText = preg_replace('/^(Passé|Présent|Futur|Le conseil qui pique mais qui aide|Le twist final)\s*:/mu', '**$1 :**', $interpretationText) ?? $interpretationText;
                @endphp

                <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 leading-relaxed [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:my-3 [&_ul]:pl-5 [&_ul]:list-disc [&_ol]:my-3 [&_ol]:pl-5 [&_ol]:list-decimal [&_li]:mb-1 [&_strong]:font-semibold">
                    {!! \Illuminate\Support\Str::markdown($interpretationText, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
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
    // Speech-to-text (mobile dictation)
    const sttStartBtn = document.getElementById('tarot-stt-start');
    const sttStopBtn = document.getElementById('tarot-stt-stop');
    const sttStatusEl = document.getElementById('tarot-stt-status');
    const questionEl = document.getElementById('question');

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

    const openAiBtn = document.getElementById('tarot-tts-openai');
    const statusEl = document.getElementById('tarot-tts-status');
    const textEl = document.getElementById('tarot-tts-text');

    if (!openAiBtn || !textEl) return;

    let openAiAudio = null;

    const setStatus = (msg) => {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
    };

    const stopOpenAi = () => {
        if (openAiAudio) {
            try { openAiAudio.pause(); } catch (e) {}
            openAiAudio = null;
        }
        setStatus('');
    };

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const speakWithOpenAi = async () => {
        // Toggle
        if (openAiAudio) {
            stopOpenAi();
            return;
        }

        const text = (textEl.textContent || '').trim();
        if (!text) {
            setStatus('Rien à lire.');
            return;
        }

        openAiBtn.disabled = true;
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
            await openAiAudio.play();
            setStatus('Lecture OpenAI…');
        } catch (e) {
            setStatus('OpenAI TTS indisponible.');
        } finally {
            openAiBtn.disabled = false;
        }
    };

    openAiBtn.addEventListener('click', speakWithOpenAi);
})();
</script>
