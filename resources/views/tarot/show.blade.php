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
                <x-secondary-button type="button" id="tarot-tts-openai">Audio (OpenAI)</x-secondary-button>
                <div id="tarot-tts-status" class="text-xs text-slate-500"></div>
            </div>

            <div class="sr-only" id="tarot-tts-text">{{ trim((string) ($reading->spoken_text ?? '')) !== '' ? (string) ($reading->spoken_text ?? '') : (string) $reading->interpretation }}</div>

            @php
                $interpretationText = (string) $reading->interpretation;
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

            <div class="flex items-center gap-3">
                <a href="{{ route('tarot.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Nouveau tirage</a>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
(() => {
    const openAiBtn = document.getElementById('tarot-tts-openai');
    const statusEl = document.getElementById('tarot-tts-status');
    const textEl = document.getElementById('tarot-tts-text');

    if (!openAiBtn || !textEl) return;

    const setStatus = (msg) => {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
    };

    let openAiAudio = null;

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

        if (!openAiBtn) return;

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
