@php
    /** @var \App\Models\User $user */
    $p = $user->astroProfile;

    $sig = is_array($user->astro_signature_json ?? null) ? (array) $user->astro_signature_json : [];

    $sunSign = trim((string) ($sig['sun_sign'] ?? ($p->western_sign ?? '')));
    $ascendant = trim((string) ($sig['ascendant'] ?? ($p->ascendant_sign ?? '')));

    $ch = is_array($sig['chinese'] ?? null) ? (array) $sig['chinese'] : [];
    $polarity = trim((string) ($ch['polarity'] ?? ($p->chinese_yin_yang ?? '')));
    $element = trim((string) ($ch['element'] ?? ($p->chinese_element ?? '')));
    $animal = trim((string) ($ch['animal'] ?? ($p->chinese_animal ?? '')));

    $chinese = trim(implode(' ', array_values(array_filter([$polarity, $element, $animal], fn ($v) => trim((string) $v) !== ''))));

    $lifePath = $sig['life_path'] ?? ($p->life_path ?? null);
    $numerology = $lifePath !== null && $lifePath !== '' ? 'Chemin de vie ' . (string) $lifePath : '';

    $archetype = trim((string) ($sig['archetype'] ?? ($p->archetype ?? '')));
    $talents = $sig['talents'] ?? ($p->talents ?? []);
    $talents = is_array($talents) ? array_values(array_filter(array_map('strval', $talents))) : [];
    $vigilance = trim((string) ($sig['vigilance'] ?? ($p->weakness ?? '')));

    $isSelf = auth()->check() && auth()->id() === $user->id;
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-xs text-slate-500">Astro (fun)</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">Fiche astrale</div>
        </div>

        @if($p && $p->computed_at)
            <div class="text-xs text-slate-500">Maj {{ $p->computed_at->diffForHumans() }}</div>
        @endif
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs text-slate-500">Soleil</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $sunSign !== '' ? $sunSign : '—' }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs text-slate-500">Ascendant</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $ascendant !== '' ? $ascendant : '—' }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Signe chinois</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $chinese !== '' ? $chinese : '—' }}</div>
            <div class="mt-1 text-xs text-slate-500">(inclut parfois Yin/Yang + élément)</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Numérologie</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $numerology !== '' ? $numerology : '—' }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs text-slate-500">Archétype</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $archetype !== '' ? $archetype : '—' }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Talents</div>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($talents as $t)
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ $t }}</span>
                @endforeach
                @if(empty($talents))
                    <span class="text-sm text-slate-500">—</span>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Point de vigilance</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $vigilance !== '' ? $vigilance : '—' }}</div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" id="astro-tarot-card" data-status-url="{{ route('astro.card.status') }}" data-generate-url="{{ route('astro.card.generate') }}">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs text-slate-500">Tarot Moderne</div>
                <div class="mt-1 text-lg font-semibold text-slate-900">Carte Tarot</div>
                <div class="mt-1 text-xs text-slate-500">Générée depuis la signature JSON (pas depuis le texte affiché).</div>
            </div>

            @if($user->astro_card_generated_at)
                <div class="text-xs text-slate-500">Maj {{ $user->astro_card_generated_at->diffForHumans() }}</div>
            @endif
        </div>

        @php
            $status = (string) ($user->astro_card_status ?? '');
            $imageUrl = trim((string) ($user->astro_card_image_url ?? ''));
            $error = trim((string) ($user->astro_card_error ?? ''));
        @endphp

        <div class="mt-4" data-state>
            @if($status === 'ready' && $imageUrl !== '')
                <div class="grid gap-4 sm:grid-cols-[minmax(0,320px)_1fr]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <img src="{{ $imageUrl }}" alt="Carte Tarot" class="aspect-[2/3] w-full object-cover" loading="lazy">
                    </div>
                    <div class="text-sm text-slate-600">
                        <div class="font-semibold text-slate-900">Prête</div>
                        <div class="mt-1">Format 2:3 · style <span class="font-mono">{{ $user->astro_card_style ?? 'tarot_modern' }}</span></div>

                        @if($isSelf)
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" data-action="regen" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Regénérer</button>
                            </div>
                            <div class="mt-2 text-xs text-slate-500">(limite quotidienne hors admin)</div>
                        @endif
                    </div>
                </div>
            @elseif($status === 'pending')
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Création en cours…</div>
                    <div class="mt-1 text-xs text-slate-500">Ça peut prendre ~10–30s.</div>
                    <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full w-1/2 animate-pulse rounded-full bg-slate-400"></div>
                    </div>
                </div>
            @elseif($status === 'error')
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                    <div class="text-sm font-semibold text-red-900">Erreur</div>
                    <div class="mt-1 text-xs text-red-800" data-error>{{ $error !== '' ? $error : 'Une erreur est survenue.' }}</div>
                    @if($isSelf)
                        <div class="mt-3">
                            <button type="button" data-action="retry" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Réessayer</button>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Pas encore générée</div>
                    <div class="mt-1 text-xs text-slate-500">Une carte premium « Tarot Moderne » basée sur ta fiche astrale.</div>
                    @if($isSelf)
                        <div class="mt-3">
                            <button type="button" data-action="generate" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Générer ma carte (Tarot)</button>
                        </div>
                    @else
                        <div class="mt-2 text-xs text-slate-500">(disponible sur le profil du membre)</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4 text-xs text-slate-500">
        Note: le thème natal complet (planètes/maisons/aspects) n’est pas encore calculé automatiquement sans provider externe.
    </div>
</div>

<script>
(() => {
    const root = document.getElementById('astro-tarot-card');
    if (!root) return;

    const isSelf = @json($isSelf);
    if (!isSelf) return;

    const statusUrl = root.getAttribute('data-status-url');
    const generateUrl = root.getAttribute('data-generate-url');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const stateEl = root.querySelector('[data-state]');
    if (!statusUrl || !generateUrl || !stateEl) return;

    let polling = null;
    let tries = 0;

    const fetchJson = async (url, opts = {}) => {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                ...(opts.headers || {}),
            },
            ...opts,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    };

    const renderPending = () => {
        stateEl.innerHTML = `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-900">Création en cours…</div>
                <div class="mt-1 text-xs text-slate-500">Ça peut prendre ~10–30s.</div>
                <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full w-1/2 animate-pulse rounded-full bg-slate-400"></div>
                </div>
            </div>
        `;
    };

    const renderError = (message) => {
        stateEl.innerHTML = `
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                <div class="text-sm font-semibold text-red-900">Erreur</div>
                <div class="mt-1 text-xs text-red-800">${message || 'Une erreur est survenue.'}</div>
                <div class="mt-3">
                    <button type="button" data-action="retry" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Réessayer</button>
                </div>
            </div>
        `;
    };

    const renderReady = (imageUrl) => {
        stateEl.innerHTML = `
            <div class="grid gap-4 sm:grid-cols-[minmax(0,320px)_1fr]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <img src="${imageUrl}" alt="Carte Tarot" class="aspect-[2/3] w-full object-cover" loading="lazy">
                </div>
                <div class="text-sm text-slate-600">
                    <div class="font-semibold text-slate-900">Prête</div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" data-action="regen" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Regénérer</button>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">(limite quotidienne hors admin)</div>
                </div>
            </div>
        `;
    };

    const stopPolling = () => {
        if (polling) {
            clearInterval(polling);
            polling = null;
        }
    };

    const startPolling = () => {
        stopPolling();
        tries = 0;
        polling = setInterval(async () => {
            tries += 1;
            const { ok, data } = await fetchJson(statusUrl, { method: 'GET' });
            if (!ok) return;

            if (data.status === 'ready' && data.image_url) {
                stopPolling();
                renderReady(data.image_url);
                return;
            }

            if (data.status === 'error') {
                stopPolling();
                renderError(data.error || 'Une erreur est survenue.');
                return;
            }

            if (tries >= 15) {
                stopPolling();
            }
        }, 2000);
    };

    const generate = async (force = false) => {
        renderPending();
        const { ok, status, data } = await fetchJson(generateUrl, {
            method: 'POST',
            body: JSON.stringify(force ? { force: true } : {}),
        });

        if (!ok && status !== 202) {
            renderError(data?.error || 'Impossible de lancer la génération.');
            return;
        }

        startPolling();
    };

    root.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const action = btn.getAttribute('data-action');
        if (action === 'generate' || action === 'retry') {
            generate(false);
        }
        if (action === 'regen') {
            generate(true);
        }
    });

    // If we land here while pending, start polling.
    const initialStatus = @json((string) ($user->astro_card_status ?? ''));
    if (initialStatus === 'pending') {
        startPolling();
    }
})();
</script>
