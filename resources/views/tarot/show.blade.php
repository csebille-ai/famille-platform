@php
    $spreadLabel = fn (string $s) => $s === 'five' ? '5 cartes' : '3 cartes';
@endphp

<x-app-layout pageBgClass="bg-slate-50">
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

        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
            <div>
                <div class="text-sm text-slate-500">Question</div>
                <div class="mt-1 text-base font-semibold text-gray-900">{{ $reading->question }}</div>
            </div>

            @if (((string) $reading->spread) === 'five')
                @php
                    $pos = [
                        ['row' => 1, 'col' => 2],
                        ['row' => 2, 'col' => 1],
                        ['row' => 2, 'col' => 2],
                        ['row' => 2, 'col' => 3],
                        ['row' => 3, 'col' => 2],
                    ];
                @endphp
                <div class="grid grid-cols-3 gap-3">
                    @foreach ((array) ($reading->cards ?? []) as $i => $c)
                        @php $p = $pos[(int) $i] ?? ['row' => 1, 'col' => 1]; @endphp
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
                    @foreach ((array) ($reading->cards ?? []) as $c)
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
                        <div class="text-sm font-semibold text-gray-900">{{ $c['name'] ?? '' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $c['keywords'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-3">
                <x-secondary-button type="button" id="tarot-tts-read">Lire</x-secondary-button>
                <x-secondary-button type="button" id="tarot-tts-stop">Stop</x-secondary-button>
                <div id="tarot-tts-status" class="text-xs text-slate-500"></div>
            </div>

            <div class="sr-only" id="tarot-tts-text">{{ trim((string) ($reading->spoken_text ?? '')) !== '' ? (string) ($reading->spoken_text ?? '') : (string) $reading->interpretation }}</div>

            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 whitespace-pre-wrap">
                {{ $reading->interpretation }}
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('tarot.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Nouveau tirage</a>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
(() => {
    const readBtn = document.getElementById('tarot-tts-read');
    const stopBtn = document.getElementById('tarot-tts-stop');
    const statusEl = document.getElementById('tarot-tts-status');
    const textEl = document.getElementById('tarot-tts-text');

    if (!readBtn || !stopBtn || !textEl) return;

    const hasTts = typeof window !== 'undefined'
        && 'speechSynthesis' in window
        && typeof window.SpeechSynthesisUtterance !== 'undefined';

    const setStatus = (msg) => {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
    };

    if (!hasTts) {
        readBtn.disabled = true;
        stopBtn.disabled = true;
        setStatus('Lecture audio non supportée sur ce navigateur.');
        return;
    }

    const preferredLang = 'fr-CA';
    const fallbackLang = 'fr-FR';

    const getVoices = () => window.speechSynthesis.getVoices?.() ?? [];

    const pickVoice = () => {
        const voices = getVoices();
        if (!voices.length) return null;
        const byLang = (lang) => voices.find(v => (v.lang || '').toLowerCase() === lang.toLowerCase())
            || voices.find(v => (v.lang || '').toLowerCase().startsWith(lang.toLowerCase()));

        return byLang(preferredLang)
            || byLang(fallbackLang)
            || byLang('fr')
            || voices[0]
            || null;
    };

    const stop = () => {
        window.speechSynthesis.cancel();
        setStatus('');
    };

    const speak = () => {
        stop();

        const text = (textEl.textContent || '').trim();
        if (!text) {
            setStatus('Rien à lire.');
            return;
        }

        const u = new SpeechSynthesisUtterance(text);
        const voice = pickVoice();

        if (voice) {
            u.voice = voice;
            u.lang = voice.lang || preferredLang;
            setStatus(`Voix: ${voice.name}${voice.lang ? ' (' + voice.lang + ')' : ''}`);
        } else {
            u.lang = preferredLang;
            setStatus('');
        }

        u.rate = 1;
        u.pitch = 1;
        u.onend = () => setStatus('');
        u.onerror = () => setStatus('Lecture audio interrompue.');

        window.speechSynthesis.speak(u);
    };

    readBtn.addEventListener('click', speak);
    stopBtn.addEventListener('click', stop);

    if (typeof window.speechSynthesis.onvoiceschanged !== 'undefined') {
        window.speechSynthesis.onvoiceschanged = () => {
            pickVoice();
        };
    }
})();
</script>
