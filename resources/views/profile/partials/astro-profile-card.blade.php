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
    $isAdmin = auth()->check() && (auth()->user()?->can('manage-users') === true);
    $canGenerateTarot = $isSelf || $isAdmin;

    $tarotStatusUrl = $isSelf
        ? route('astro.card.status')
        : ($isAdmin ? route('astro.card.statusForUser', $user) : null);

    $tarotGenerateUrl = $isSelf
        ? route('astro.card.generate')
        : ($isAdmin ? route('astro.card.generateForUser', $user) : null);

    $useAsIconUrl = $isSelf ? route('profile.avatar.useAstroIcon') : null;
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="mt-1 text-lg font-semibold text-slate-900">Fiche astrale</div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-3">
            <div class="rounded-xl border p-3 sm:p-4" style="background: rgba(79, 70, 229, 0.08); border-color: rgba(79, 70, 229, 0.28);">
                <div class="text-xs font-semibold" style="color: #3730a3;">Soleil</div>
                <div class="mt-1 text-base font-extrabold text-slate-900">{{ $sunSign !== '' ? $sunSign : '—' }}</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                <div class="text-xs text-slate-500">Signe chinois</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $chinese !== '' ? $chinese : '—' }}</div>
            </div>

            <div class="rounded-xl border p-3 sm:p-4" style="background: rgba(217, 119, 6, 0.10); border-color: rgba(217, 119, 6, 0.30);">
                <div class="text-xs font-semibold" style="color: #92400e;">Archétype</div>
                <div class="mt-1 text-sm font-bold text-slate-900">{{ $archetype !== '' ? $archetype : '—' }}</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                <div class="text-xs text-slate-500">Point de vigilance</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $vigilance !== '' ? $vigilance : '—' }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
                <div class="text-xs text-slate-500">Ascendant</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $ascendant !== '' ? $ascendant : '—' }}</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                <div class="text-xs text-slate-500">Numérologie</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $numerology !== '' ? $numerology : '—' }}</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
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
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" id="astro-blason-card" data-status-url="{{ $tarotStatusUrl }}" data-generate-url="{{ $tarotGenerateUrl }}" data-use-as-icon-url="{{ $useAsIconUrl }}">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="mt-1 text-lg font-semibold text-slate-900">Blason</div>
            </div>
        </div>

        @php
            $status = (string) ($user->astro_card_status ?? '');
            $imageUrl = trim((string) ($user->astro_card_image_url ?? ''));
            $iconUrl = trim((string) ($user->astro_card_icon_url ?? ''));
            $displayUrl = '';
            if ($imageUrl !== '') {
                $displayUrl = $isSelf
                    ? route('astro.card.image')
                    : route('astro.card.imagePublic', $user);
            }

            $iconDisplayUrl = '';
            if ($iconUrl !== '') {
                $iconDisplayUrl = $isSelf
                    ? route('astro.card.icon')
                    : route('astro.card.iconPublic', $user);
            }

            // Cache-bust stable image endpoints so regeneration always shows the latest.
            $v = optional($user->astro_card_generated_at)->getTimestamp() ?? time();
            if ($displayUrl !== '') {
                $displayUrl .= (str_contains($displayUrl, '?') ? '&' : '?') . 'v=' . $v;
            }
            if ($iconDisplayUrl !== '') {
                $iconDisplayUrl .= (str_contains($iconDisplayUrl, '?') ? '&' : '?') . 'v=' . $v;
            }
            $error = trim((string) ($user->astro_card_error ?? ''));

        @endphp

        <div class="mt-4" data-state>
            @if($status === 'ready' && $imageUrl !== '' && $iconUrl !== '')
                <div class="grid gap-4 sm:grid-cols-[minmax(0,320px)_1fr]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <div class="relative w-full" style="padding-bottom:150%;">
                            <img src="{{ $displayUrl }}" data-external-src="{{ $imageUrl }}" alt="Blason (carte)" class="absolute inset-0 h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.dataset.triedExternal==='1'){this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');} else {this.dataset.triedExternal='1'; if(this.dataset.externalSrc){this.src=this.dataset.externalSrc;} else {this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');}}">

                            <div class="hidden absolute inset-0 p-3 text-center text-xs text-red-800" data-img-fail>
                                <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                                    Impossible de charger l’image.
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="text-sm text-slate-600">
                        <div class="flex items-center gap-3">
                            <div class="h-14 w-14 overflow-hidden rounded-full border border-slate-200 bg-slate-50">
                                <img src="{{ $iconDisplayUrl }}" data-external-src="{{ $iconUrl }}" alt="Blason (icône)" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.dataset.triedExternal==='1'){this.style.display='none';} else {this.dataset.triedExternal='1'; if(this.dataset.externalSrc){this.src=this.dataset.externalSrc;} else {this.style.display='none';}}">
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Icône</div>
                                <div class="text-sm font-semibold text-slate-900">1:1</div>
                            </div>
                        </div>
                        @if($canGenerateTarot)
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" data-action="regen" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Regénérer</button>
                                @if($isSelf)
                                    <button type="button" data-action="use-icon" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Utiliser comme icône</button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @elseif($status === 'ready' && $imageUrl !== '' && $iconUrl === '')
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-semibold text-amber-900">Mise à jour requise</div>
                    <div class="mt-1 text-xs text-amber-800">Ton blason a été généré avant l’ajout de l’icône 1:1. Regénère pour l’obtenir.</div>
                    @if($canGenerateTarot)
                        <div class="mt-3">
                            <button type="button" data-action="regen" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Regénérer</button>
                        </div>
                    @endif
                </div>
            @elseif($status === 'pending')
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Création en cours…</div>
                    <div class="microcopy mt-1 text-xs text-slate-500">Ça peut prendre ~10–30s.</div>
                    <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full w-1/2 animate-pulse rounded-full bg-slate-400"></div>
                    </div>
                </div>
            @elseif($status === 'error')
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                    <div class="text-sm font-semibold text-red-900">Erreur</div>
                    <div class="mt-1 text-xs text-red-800" data-error>{{ $error !== '' ? $error : 'Une erreur est survenue.' }}</div>
                    @if($canGenerateTarot)
                        <div class="mt-3">
                            <button type="button" data-action="retry" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Réessayer</button>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Pas encore générée</div>
                    <div class="microcopy mt-1 text-xs text-slate-500">Un blason premium basé sur ta fiche astrale (carte 2:3 + icône 1:1).</div>
                    @if($canGenerateTarot)
                        <div class="mt-3">
                            <button type="button" data-action="generate" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Générer mon blason</button>
                        </div>
                    @else
                        <div class="microcopy mt-2 text-xs text-slate-500">(disponible sur le profil du membre)</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

</div>

<script>
(() => {
    const root = document.getElementById('astro-blason-card');
    if (!root) return;

    const canGenerate = @json($canGenerateTarot);
    if (!canGenerate) return;

    const statusUrl = root.getAttribute('data-status-url');
    const generateUrl = root.getAttribute('data-generate-url');
    const useAsIconUrl = root.getAttribute('data-use-as-icon-url');
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

    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const renderReady = (imageUrl, externalUrl = null, iconUrl = null, iconExternalUrl = null) => {

        stateEl.innerHTML = `
            <div class="grid gap-4 sm:grid-cols-[minmax(0,320px)_1fr]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <div class="relative w-full" style="padding-bottom:150%;">
                        <img src="${escapeHtml(imageUrl)}" data-external-src="${escapeHtml(externalUrl || '')}" alt="Blason (carte)" class="absolute inset-0 h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.dataset.triedExternal==='1'){this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');} else {this.dataset.triedExternal='1'; if(this.dataset.externalSrc){this.src=this.dataset.externalSrc;} else {this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');}}">
                        <div class="hidden absolute inset-0 p-3 text-center text-xs text-red-800" data-img-fail>
                            <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                                Impossible de charger l’image.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-sm text-slate-600">
                    ${iconUrl ? `
                        <div class="flex items-center gap-3">
                            <div class="h-14 w-14 overflow-hidden rounded-full border border-slate-200 bg-slate-50">
                                <img src="${escapeHtml(iconUrl)}" data-external-src="${escapeHtml(iconExternalUrl || '')}" alt="Blason (icône)" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.dataset.triedExternal==='1'){this.style.display='none';} else {this.dataset.triedExternal='1'; if(this.dataset.externalSrc){this.src=this.dataset.externalSrc;} else {this.style.display='none';}}">
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Icône</div>
                                <div class="text-sm font-semibold text-slate-900">1:1</div>
                            </div>
                        </div>
                    ` : ''}
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" data-action="regen" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Regénérer</button>
                    </div>
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

            const displayUrl = data?.image_display_url || data?.image_url;
            const externalUrl = data?.image_url;

            const iconDisplayUrl = data?.icon_display_url || data?.icon_url;
            const iconExternalUrl = data?.icon_url;

            const v = encodeURIComponent(String(data?.generated_at || Date.now()));
            const bust = (url) => {
                if (!url) return url;
                const s = String(url);
                return s + (s.includes('?') ? '&' : '?') + 'v=' + v;
            };

            if (data.status === 'ready' && displayUrl && iconDisplayUrl) {
                stopPolling();
                renderReady(bust(displayUrl), bust(externalUrl), bust(iconDisplayUrl), bust(iconExternalUrl));
                return;
            }

            if (data.status === 'ready' && (!displayUrl || !iconDisplayUrl)) {
                stopPolling();
                renderError("Blason généré, mais URL publique manquante. Vérifie R2_PUBLIC_BASE_URL (et que le bucket est bien servi en public).");
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
        if (action === 'use-icon') {
            if (!useAsIconUrl) return;
            btn.disabled = true;
            btn.textContent = '…';
            fetchJson(useAsIconUrl, { method: 'POST', body: JSON.stringify({}) })
                .then(({ ok, data }) => {
                    if (!ok) {
                        btn.disabled = false;
                        btn.textContent = 'Utiliser comme icône';
                        const msg = data?.error || "Impossible d'activer l'icône.";
                        renderError(msg);
                        return;
                    }
                    window.location.reload();
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Utiliser comme icône';
                    renderError("Impossible d'activer l'icône.");
                });
        }
    });

    // If we land here while pending, start polling.
    const initialStatus = @json((string) ($user->astro_card_status ?? ''));
    if (initialStatus === 'pending') {
        startPolling();
    }
})();
</script>
