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
                    <x-secondary-button type="button" id="tarot-tts-openai">Audio (OpenAI)</x-secondary-button>
                    <div id="tarot-tts-status" class="text-xs text-slate-500"></div>
                </div>

                <div class="sr-only" id="tarot-tts-text">{{ trim((string) ($draft['spoken_text'] ?? '')) !== '' ? (string) ($draft['spoken_text'] ?? '') : (string) ($draft['interpretation'] ?? '') }}</div>

                @php
                    $interpretationText = (string) ($draft['interpretation'] ?? '');
                    $maybeJson = trim($interpretationText);
                    if ($maybeJson !== '' && str_starts_with($maybeJson, '{')) {
                        $decoded = json_decode($maybeJson, true);
                        if (is_array($decoded) && isset($decoded['interpretation'])) {
                            $interpretationText = (string) $decoded['interpretation'];
                        }
                    }
                @endphp

                <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 leading-relaxed">
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
