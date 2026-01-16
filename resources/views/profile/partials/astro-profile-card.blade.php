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

    $chinese = \App\Services\Astro\ChineseZodiac::formatDisplayLabel($animal, $element, $polarity);

    $kemeticIndex = (int) ($sig['kemetic_decan_index'] ?? ($p->kemetic_decan_index ?? 0));
    $kemeticLabel = trim((string) ($sig['kemetic_decan_label'] ?? ($p->kemetic_decan_label ?? '')));
    $kemeticKeyword = trim((string) ($sig['kemetic_decan_keyword'] ?? ($p->kemetic_decan_keyword ?? '')));

    $kemeticLine = $kemeticLabel;
    if ($kemeticIndex > 0) {
        $kemeticLine = ($kemeticLine !== '' ? ('#' . $kemeticIndex . ' · ' . $kemeticLine) : ('#' . $kemeticIndex));
    }

    $archetype = trim((string) ($sig['archetype'] ?? ($p->archetype ?? '')));
    $talents = $sig['talents'] ?? ($p->talents ?? []);
    $talents = is_array($talents) ? array_values(array_filter(array_map('strval', $talents))) : [];
    $vigilance = trim((string) ($sig['vigilance'] ?? ($p->weakness ?? '')));

    $isSelf = auth()->check() && auth()->id() === $user->id;
    $isAdmin = auth()->check() && (auth()->user()?->can('manage-users') === true);
    $canGenerateAvatarAstro = $isSelf || $isAdmin;

    $avatarStatusUrl = $isSelf
        ? route('avatar.astro.status')
        : ($isAdmin ? route('avatar.astro.statusForUser', $user) : null);

    $avatarGenerateUrl = $isSelf
        ? route('avatar.astro.generate')
        : ($isAdmin ? route('avatar.astro.generateForUser', $user) : null);
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
                <div class="text-xs text-slate-500">Décan kémétique</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $kemeticLine !== '' ? $kemeticLine : '—' }}</div>
                @if($kemeticKeyword !== '')
                    <div class="mt-1 text-xs text-slate-500">{{ $kemeticKeyword }}</div>
                @endif
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

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" id="avatar-astro-card" data-status-url="{{ $avatarStatusUrl }}" data-generate-url="{{ $avatarGenerateUrl }}">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="mt-1 text-lg font-semibold text-slate-900">Avatar Astro</div>
            </div>
        </div>

        @php
            $status = (string) ($user->avatar_astro_status ?? '');
            $imageUrl = trim((string) ($user->avatar_image_url ?? ''));
            $displayUrl = '';
            if ($imageUrl !== '') {
                $displayUrl = $isSelf
                    ? route('avatar.astro.image')
                    : route('avatar.astro.imagePublic', $user);
            }

            $v = optional($user->avatar_updated_at)->getTimestamp() ?? time();
            if ($displayUrl !== '') {
                $displayUrl .= (str_contains($displayUrl, '?') ? '&' : '?') . 'v=' . $v;
            }

            $error = trim((string) ($user->avatar_astro_error ?? ''));
            $spec = is_array($user->avatar_spec_json ?? null) ? (array) $user->avatar_spec_json : [];
            $sunElement = (string) ($spec['sun_element'] ?? '');
            $chAnimal = (string) ($spec['chinese_animal'] ?? '');
            $kemetic = (int) ($spec['kemetic_decan_index'] ?? 0);
            $kemeticSpecLabel = trim((string) ($spec['kemetic_decan_label'] ?? ''));

            $traitsSur = is_array($user->avatar_traits_surannes ?? null) ? (array) $user->avatar_traits_surannes : [];
            $traitsSurLine = implode(', ', array_values(array_filter(array_map('strval', $traitsSur))));

            $elementIconKey = strtolower(match ($sunElement) {
                'Terre' => 'earth',
                'Feu' => 'fire',
                'Air' => 'air',
                'Eau' => 'water',
                default => '',
            });

            $elementIcon = $elementIconKey !== '' ? asset('icons/astro/elements/' . $elementIconKey . '.svg') : asset('icons/astro/_default.svg');

            $chSlug = strtolower($chAnimal);
            $chPath = $chSlug !== '' ? public_path('icons/astro/chinese/' . $chSlug . '.svg') : '';
            $chIcon = ($chPath !== '' && file_exists($chPath))
                ? asset('icons/astro/chinese/' . $chSlug . '.svg')
                : asset('icons/astro/_default.svg');

            $totemCanon = $chAnimal !== '' ? \App\Services\AvatarAstro\ArchetypeAndTraits::chineseTrait($chAnimal) : '';
            $kemCanon = $kemetic > 0 ? \App\Services\AvatarAstro\ArchetypeAndTraits::kemeticDecanTrait($kemetic) : '';
            $surMapper = app(\App\Services\AvatarAstro\TraitsSurannesMapper::class);
            $totemSur = $totemCanon !== '' ? $surMapper->toSuranne((int) $user->id, $totemCanon) : '';
            $kemSur = $kemCanon !== '' ? $surMapper->toSuranne((int) $user->id, $kemCanon) : '';

            $archetypeTitle = trim((string) ($user->avatar_archetype_title ?? ''));
        @endphp

        <div class="mt-4" data-state>
            @if($status === 'ready' && $imageUrl !== '')
                <div class="grid gap-4 sm:grid-cols-[minmax(0,260px)_1fr]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <div class="relative w-full" style="padding-bottom:100%;">
                            <img src="{{ $displayUrl }}" data-external-src="{{ $imageUrl }}" alt="Avatar Astro" class="absolute inset-0 h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.dataset.triedExternal==='1'){this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');} else {this.dataset.triedExternal='1'; if(this.dataset.externalSrc){this.src=this.dataset.externalSrc;} else {this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');}}">

                            <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/85 px-3 py-1 text-[11px] font-semibold text-slate-800 shadow-sm">
                                    <img src="{{ $chIcon }}" alt="" class="h-4 w-4" loading="lazy" />
                                    <span>{{ $totemSur !== '' ? $totemSur : ($chAnimal !== '' ? $chAnimal : 'mystère') }}</span>
                                </span>

                                @if($kemetic > 0)
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/85 px-3 py-1 text-[11px] font-semibold text-slate-800 shadow-sm">
                                        <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-slate-900 px-1 text-[10px] font-extrabold text-white">{{ $kemetic }}</span>
                                        <span>{{ $kemSur !== '' ? $kemSur : ($kemeticSpecLabel !== '' ? $kemeticSpecLabel : 'Décan kémétique') }}</span>
                                    </span>
                                @endif

                                @if($sunElement !== '')
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/85 px-3 py-1 text-[11px] font-semibold text-slate-800 shadow-sm">
                                        <img src="{{ $elementIcon }}" alt="" class="h-4 w-4" loading="lazy" />
                                        <span>{{ $sunElement }}</span>
                                    </span>
                                @endif
                            </div>

                            <div class="hidden absolute inset-0 p-3 text-center text-xs text-red-800" data-img-fail>
                                <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                                    Impossible de charger l’image.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-sm text-slate-700">
                        <div class="text-base font-semibold text-slate-900">{{ $archetypeTitle !== '' ? $archetypeTitle : '—' }}</div>
                        <div class="mt-1 text-sm text-slate-600">{{ $traitsSurLine !== '' ? $traitsSurLine : '—' }}</div>

                        @if($canGenerateAvatarAstro)
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" data-action="regen" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Regénérer</button>
                            </div>
                        @endif
                    </div>
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
                    @if($canGenerateAvatarAstro)
                        <div class="mt-3">
                            <button type="button" data-action="retry" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Réessayer</button>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Pas encore généré</div>
                    <div class="microcopy mt-1 text-xs text-slate-500">Portrait 1:1 (tête + épaules), ambiance astro via palette (sans symboles, sans texte).</div>
                    @if($canGenerateAvatarAstro)
                        <div class="mt-3">
                            <button type="button" data-action="generate" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Générer mon Avatar Astro</button>
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
    const root = document.getElementById('avatar-astro-card');
    if (!root) return;

    const canGenerate = @json($canGenerateAvatarAstro);
    if (!canGenerate) return;

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

    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const renderReady = (imageUrl, externalUrl = null) => {
        stateEl.innerHTML = `
            <div class="grid gap-4 sm:grid-cols-[minmax(0,260px)_1fr]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <div class="relative w-full" style="padding-bottom:100%;">
                        <img src="${escapeHtml(imageUrl)}" data-external-src="${escapeHtml(externalUrl || '')}" alt="Avatar Astro" class="absolute inset-0 h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.dataset.triedExternal==='1'){this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');} else {this.dataset.triedExternal='1'; if(this.dataset.externalSrc){this.src=this.dataset.externalSrc;} else {this.style.display='none'; this.parentElement?.querySelector('[data-img-fail]')?.classList.remove('hidden');}}">
                        <div class="hidden absolute inset-0 p-3 text-center text-xs text-red-800" data-img-fail>
                            <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                                Impossible de charger l’image.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-sm text-slate-600">
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

            const v = encodeURIComponent(String(data?.updated_at || Date.now()));
            const bust = (url) => {
                if (!url) return url;
                const s = String(url);
                return s + (s.includes('?') ? '&' : '?') + 'v=' + v;
            };

            if (data.status === 'ready' && displayUrl) {
                stopPolling();
                // Reload so we re-render chips + archetype + traits from fresh DB.
                window.location.reload();
                return;
            }

            if (data.status === 'ready' && !displayUrl) {
                stopPolling();
                renderError("Avatar généré, mais URL publique manquante. Vérifie R2_PUBLIC_BASE_URL (et que le bucket est bien servi en public).");
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

    const initialStatus = @json((string) ($user->avatar_astro_status ?? ''));
    if (initialStatus === 'pending') {
        startPolling();
    }
})();
</script>
