<x-app-layout>
    <x-slot name="header">
        @php
            $role = (string) ($user->role ?? 'member');
            $inviteState = $user->invited_at ? 'Envoyée' : 'En attente';

            $roleBadge = match ($role) {
                'admin' => 'bg-violet-50 text-violet-700 border-violet-200',
                'editor' => 'bg-sky-50 text-sky-700 border-sky-200',
                default => 'bg-slate-50 text-slate-700 border-slate-200',
            };

            $inviteBadge = $user->invited_at
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                : 'bg-amber-50 text-amber-900 border-amber-200';

            $hasAvatar = trim((string) ($user->avatar_image_url ?? '')) !== '';
            $avatarV = optional($user->avatar_updated_at)->getTimestamp() ?? time();
            $avatarUrl = route('avatar.astro.imageForUser', ['user' => $user, 'v' => $avatarV]);
        @endphp

        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <a
                    href="{{ route('admin.users.index') }}"
                    class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                    aria-label="Retour"
                    title="Retour"
                >
                    <i class="ph ph-caret-left" aria-hidden="true"></i>
                </a>

                <div class="h-12 w-12 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                    @if($hasAvatar)
                        <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" />
                    @else
                        <div class="text-slate-600 font-semibold">
                            {{ $user->initials() }}
                        </div>
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="text-xl font-semibold text-slate-900 truncate">{{ $user->name }}</div>
                    <div class="mt-0.5 text-sm text-slate-500 truncate">{{ $user->email }}</div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $roleBadge }}">
                            {{ $role }}
                        </span>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $inviteBadge }}">
                            Invitation: {{ $inviteState }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="shrink-0">
                <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center h-10 px-4 rounded-2xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                    Modifier
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $dob = $user->date_of_birth;
        $birthTime = trim((string) ($user->birth_time ?? ''));
        $birthPlace = trim((string) ($user->birth_place ?? ''));
        $tz = 'Europe/Paris';
        $hasCoords = ($user->birth_latitude !== null && $user->birth_longitude !== null);

        $precision = 'unknown';
        if ($dob) {
            $precision = 'approx';
            if ($birthTime !== '' && $birthPlace !== '' && $hasCoords) {
                $precision = 'exact';
            }
        }

        $sig = is_array($user->astro_signature_json ?? null) ? (array) $user->astro_signature_json : [];
        $p = $user->astroProfile;
        $sun = trim((string) ($sig['sun_sign'] ?? ($p?->western_sign ?? '')));
        $moon = trim((string) ($sig['moon_sign'] ?? ($p?->moon_sign ?? '')));
        $asc = trim((string) ($sig['ascendant'] ?? ($p?->ascendant_sign ?? '')));

        $pill = function (string $label, string $value, string $missing = '—') {
            $value = trim($value);
            return [
                'label' => $label,
                'value' => $value !== '' ? $value : $missing,
                'missing' => $value === '',
            ];
        };
        $pills = [
            $pill('Soleil', $sun),
            $pill('Lune', $moon, 'Non dispo'),
            $pill('Ascendant', $asc),
        ];
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="mx-4 sm:mx-0 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-900">Statut</div>
                </div>

                <div class="mt-3 divide-y divide-slate-100">
                    <div class="py-2 flex items-center justify-between gap-4">
                        <div class="text-xs text-slate-500">Rôle</div>
                        <div class="text-sm font-semibold text-slate-900">{{ $role }}</div>
                    </div>
                    <div class="py-2 flex items-center justify-between gap-4">
                        <div class="text-xs text-slate-500">Invitation</div>
                        <div class="text-sm font-semibold text-slate-900">{{ $inviteState }}</div>
                    </div>
                    <div class="py-2 flex items-center justify-between gap-4">
                        <div class="text-xs text-slate-500">Inscription</div>
                        <div class="text-sm font-semibold text-slate-900">{{ optional($user->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div class="py-2 flex items-center justify-between gap-4">
                        <div class="text-xs text-slate-500">Dernière mise à jour</div>
                        <div class="text-sm font-semibold text-slate-900">{{ optional($user->updated_at)->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-4" x-data="{ showCoords: false }">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-900">Naissance (astro)</div>
                    <div class="text-xs text-slate-500">
                        Précision:
                        <span class="font-semibold text-slate-700">{{ $precision }}</span>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-[11px] font-semibold text-slate-500">Date de naissance</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $dob ? $dob->format('Y-m-d') : '—' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-[11px] font-semibold text-slate-500">Heure de naissance</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $birthTime !== '' ? $birthTime : '—' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-[11px] font-semibold text-slate-500">Lieu</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $birthPlace !== '' ? $birthPlace : '—' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-[11px] font-semibold text-slate-500">Fuseau</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $tz }}</div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if($hasCoords)
                        <button type="button" class="inline-flex items-center h-9 px-3 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50" @click="showCoords = !showCoords">
                            <span x-text="showCoords ? 'Masquer coords' : 'Afficher coords'"></span>
                        </button>
                        <div x-show="showCoords" x-transition.opacity.duration.120ms class="text-sm text-slate-700">
                            <span class="text-xs text-slate-500">Coords:</span>
                            <span class="font-semibold">{{ $user->birth_latitude }}, {{ $user->birth_longitude }}</span>
                        </div>
                    @else
                        <div class="text-xs text-slate-500">Coords: —</div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-4">
                <div class="text-sm font-semibold text-slate-900">Actions rapides</div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if(!$user->invited_at)
                        <form method="POST" action="{{ route('admin.users.invite', $user) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center h-9 px-3 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                                Renvoyer invitation
                            </button>
                        </form>
                    @endif

                    <button
                        type="button"
                        class="inline-flex items-center h-9 px-3 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50"
                        data-copy-invite
                        data-url="{{ route('admin.users.inviteLink', $user) }}"
                    >
                        Copier lien invitation
                    </button>
                </div>

                <div class="mt-2 text-xs text-slate-500" data-copy-status></div>
            </div>

            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-900">Astro</div>
                    <a href="{{ route('admin.users.astro', $user) }}" class="inline-flex items-center h-9 px-3 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">
                        Voir la fiche astro
                    </a>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach($pills as $p)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="text-[11px] font-semibold text-slate-500">{{ $p['label'] }}</div>
                            <div class="mt-1 text-sm font-semibold {{ $p['missing'] ? 'text-slate-500' : 'text-slate-900' }}">{{ $p['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const btn = document.querySelector('[data-copy-invite]');
            if (!btn) return;

            const statusEl = document.querySelector('[data-copy-status]');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const setStatus = (msg) => {
                if (statusEl) statusEl.textContent = msg || '';
            };

            btn.addEventListener('click', async () => {
                setStatus('');
                const url = btn.getAttribute('data-url');
                if (!url) return;

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                        },
                        body: JSON.stringify({}),
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data?.url) {
                        setStatus('Impossible de générer le lien.');
                        return;
                    }

                    await navigator.clipboard.writeText(String(data.url));
                    setStatus('Lien copié.');
                } catch (e) {
                    setStatus('Copie impossible (navigateur).');
                }
            });
        })();
    </script>
</x-app-layout>
