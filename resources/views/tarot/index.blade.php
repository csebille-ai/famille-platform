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
                    <x-secondary-button type="button" id="tarot-tts-read">Lire</x-secondary-button>
                    <x-secondary-button type="button" id="tarot-tts-stop">Stop</x-secondary-button>
                    <div id="tarot-tts-status" class="text-xs text-slate-500"></div>
                </div>

                <div class="sr-only" id="tarot-tts-text">{{ trim((string) ($draft['spoken_text'] ?? '')) !== '' ? (string) ($draft['spoken_text'] ?? '') : (string) ($draft['interpretation'] ?? '') }}</div>

                <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 whitespace-pre-wrap">
                    {{ (string) ($draft['interpretation'] ?? '') }}
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
            // Warm-up: makes pickVoice more reliable on some browsers.
            pickVoice();
        };
    }
})();
</script>
