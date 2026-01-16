<x-app-layout :hideNavigation="true">
    @php
        /** @var \App\Models\User $user */
        $displayName = trim((string) ($user->name ?? ''));
        $dob = $user->date_of_birth;
        $ageLabel = null;
        if ($dob) {
            try {
                $now = \Carbon\CarbonImmutable::now('Europe/Paris');
                $birth = \Carbon\CarbonImmutable::instance($dob);

                $months = $birth->diffInMonths($now);
                $years = intdiv($months, 12);
                $remMonths = $months % 12;

                $ageLabel = $years . ' ans';
                if ($remMonths > 0) {
                    $ageLabel .= ' • ' . $remMonths . ' mois';
                }
            } catch (\Throwable) {
                $ageLabel = null;
            }
        }

        $hasAvatar = trim((string) ($user->avatar_image_url ?? '')) !== '';
        $avatarV = optional($user->avatar_updated_at)->getTimestamp() ?? time();
        $avatarImageUrl = isset($avatarImageUrl)
            ? (string) $avatarImageUrl
            : route('avatar.astro.image', ['v' => $avatarV]);

        $sun = trim((string) ($astro['sun_sign'] ?? ''));
        $moon = trim((string) ($astro['moon_sign'] ?? ''));
        $asc = trim((string) ($astro['ascendant'] ?? ''));
        $precision = (string) ($astro['precision'] ?? 'unknown');

        $archetypeHero = trim((string) ($astro['archetype'] ?? ''));

        $birthCtaUrl = isset($birthCtaUrl)
            ? (string) $birthCtaUrl
            : (route('profile.edit') . '#astro-birth');

        $isChartMissing = $precision !== 'exact' || $moon === '' || $asc === '';
        $pill = function (string $label, string $value, array $opts = []) {
            $value = trim($value);
            $isMissing = ($value === '');

            $missingValue = (string) ($opts['missingValue'] ?? '—');
            $hintWhenMissing = (string) ($opts['hintWhenMissing'] ?? '');

            return [
                'label' => $label,
                'value' => $isMissing ? $missingValue : $value,
                'missing' => $isMissing,
                'hint' => $isMissing ? $hintWhenMissing : '',
            ];
        };

        $pills = [
            $pill('Soleil', $sun, ['missingValue' => '—']),
            $pill('Lune', $moon, ['missingValue' => 'Non dispo', 'hintWhenMissing' => 'Heure/lieu requis']),
            $pill('Ascendant', $asc, ['missingValue' => '—']),
        ];

        $tabs = [
            'profile' => 'Profil',
            'chart' => 'Carte',
            'places' => 'Lieux',
        ];
    @endphp

    <div x-data="{ show: false, openTalents: false }" x-init="requestAnimationFrame(() => show = true)" class="pt-[calc(env(safe-area-inset-top)+0.75rem)] pb-[calc(env(safe-area-inset-bottom)+1.5rem)]">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="mx-4 sm:mx-0 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('avatar_astro'))
                <div class="mx-4 sm:mx-0 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950">
                    {{ $errors->first('avatar_astro') }}
                </div>
            @endif

            <div class="px-4 sm:px-0">
                <div class="relative flex items-center justify-between h-12">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-800 hover:bg-slate-50"
                        aria-label="Retour"
                        onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; }"
                    >
                        <i class="ph ph-caret-left" aria-hidden="true"></i>
                    </button>

                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="text-[0.95rem] font-semibold text-slate-900">Fiche astro</div>
                    </div>

                    <a href="{{ $birthCtaUrl }}" class="inline-flex items-center h-10 px-3 rounded-2xl border border-slate-200 bg-white text-slate-800 text-sm font-semibold hover:bg-slate-50">
                        Modifier
                    </a>
                </div>
            </div>

            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-12 w-12 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                            @if($hasAvatar)
                                <img src="{{ $avatarImageUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" />
                            @else
                                <div class="text-slate-600 font-semibold">
                                    {{ $user->initials() }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="text-[15px] font-semibold text-slate-900 truncate">{{ $displayName !== '' ? $displayName : 'Profil' }}</div>
                            <div class="mt-0.5 text-[13px] text-slate-500">
                                @if($ageLabel !== null)
                                    {{ $ageLabel }}
                                @else
                                    Âge inconnu
                                @endif
                            </div>

                            @if(!$isChartMissing && $precision === 'exact')
                                <div class="mt-0.5 text-xs text-slate-500">Données complètes</div>
                            @endif
                        </div>
                    </div>

                    @if($archetypeHero !== '')
                        <div class="shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1">
                            <div class="flex items-center gap-1.5">
                                <i class="ph ph-shield text-slate-500 text-xs" aria-hidden="true"></i>
                                <div>
                                    <div class="text-[11px] leading-4 text-slate-500">Archétype</div>
                                    <div class="text-sm font-semibold leading-5 text-slate-900">{{ $archetypeHero }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-3 mt-3">
                    @foreach($pills as $p)
                        <div class="rounded-xl border border-slate-200 p-3 min-h-[74px] bg-white">
                            <div class="text-xs text-slate-500">{{ $p['label'] }}</div>
                            <div class="mt-1 text-base font-semibold {{ $p['missing'] ? 'text-slate-400' : 'text-slate-900' }}">
                                {{ $p['value'] }}
                            </div>
                            <div class="mt-1 text-xs text-slate-400 leading-4">
                                {{ $p['hint'] !== '' ? $p['hint'] : ' ' }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($isChartMissing)
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 mt-3 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1 text-sm font-medium text-amber-950 leading-5 sm:whitespace-nowrap">
                            Complète tes infos de naissance pour calculer la carte du ciel.
                        </div>
                        <a
                            href="{{ $birthCtaUrl }}"
                            class="inline-flex items-center h-9 px-3 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 active:bg-amber-800 shrink-0"
                        >
                            Compléter
                        </a>
                    </div>
                @endif

                <div class="mt-3">
                    <div class="inline-flex w-full bg-slate-100/60 border border-slate-200 rounded-xl p-1">
                        @foreach($tabs as $key => $label)
                            <a
                                href="{{ route('astro.show', ['tab' => $key]) }}"
                                class="flex-1 text-center h-10 inline-flex items-center justify-center rounded-lg text-sm font-medium transition-all duration-150 {{ $tab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}"
                            >
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($tab === 'profile')
                <div x-show="show" x-transition.opacity.duration.180ms x-transition.transform.duration.180ms class="bg-white shadow sm:rounded-2xl">
                    <div class="p-4 sm:p-6 space-y-5">
                        <div class="text-sm font-semibold text-slate-900">Essentiel</div>

                        @php
                            $chinese = trim((string) ($astro['chinese'] ?? ''));
                            $lifePath = (int) ($astro['life_path'] ?? 0);

                            $elementFromSign = function (string $sign): string {
                                $sign = mb_strtolower(trim($sign));

                                $map = [
                                    'bélier' => 'Feu',
                                    'taureau' => 'Terre',
                                    'gémeaux' => 'Air',
                                    'cancer' => 'Eau',
                                    'lion' => 'Feu',
                                    'vierge' => 'Terre',
                                    'balance' => 'Air',
                                    'scorpion' => 'Eau',
                                    'sagittaire' => 'Feu',
                                    'capricorne' => 'Terre',
                                    'verseau' => 'Air',
                                    'poissons' => 'Eau',
                                ];

                                return (string) ($map[$sign] ?? '');
                            };

                            $modalityFromSign = function (string $sign): string {
                                $sign = mb_strtolower(trim($sign));

                                $map = [
                                    'bélier' => 'Cardinal',
                                    'cancer' => 'Cardinal',
                                    'balance' => 'Cardinal',
                                    'capricorne' => 'Cardinal',
                                    'taureau' => 'Fixe',
                                    'lion' => 'Fixe',
                                    'scorpion' => 'Fixe',
                                    'verseau' => 'Fixe',
                                    'gémeaux' => 'Mutable',
                                    'vierge' => 'Mutable',
                                    'sagittaire' => 'Mutable',
                                    'poissons' => 'Mutable',
                                ];

                                return (string) ($map[$sign] ?? '');
                            };

                            $element = $sun !== '' ? $elementFromSign($sun) : '';
                            $modality = $sun !== '' ? $modalityFromSign($sun) : '';

                            $essentials = [
                                [
                                    'label' => 'Signe chinois',
                                    'value' => $chinese !== '' ? $chinese : 'À compléter',
                                    'muted' => $chinese === '',
                                ],
                                [
                                    'label' => 'Numérologie',
                                    'value' => $lifePath > 0 ? ('Chemin de vie ' . $lifePath) : 'À calculer',
                                    'muted' => $lifePath <= 0,
                                ],
                                [
                                    'label' => 'Élément',
                                    'value' => $element !== '' ? $element : 'À calculer',
                                    'muted' => $element === '',
                                ],
                                [
                                    'label' => 'Modalité',
                                    'value' => $modality !== '' ? $modality : 'À calculer',
                                    'muted' => $modality === '',
                                ],
                            ];
                        @endphp

                        <div class="sm:hidden overflow-x-auto -mx-4 px-4">
                            <div class="flex gap-3 snap-x snap-mandatory pb-1">
                                @foreach($essentials as $e)
                                    <div class="w-[240px] shrink-0 snap-start rounded-xl border border-slate-200 bg-white p-3 min-h-[76px]">
                                        <div class="text-[11px] font-semibold text-slate-500">{{ $e['label'] }}</div>
                                        <div class="mt-1 text-sm font-semibold {{ $e['muted'] ? 'text-slate-500' : 'text-slate-900' }}">{{ $e['value'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="hidden sm:grid sm:grid-cols-2 gap-3">
                            @foreach($essentials as $e)
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 min-h-[76px]">
                                    <div class="text-[11px] font-semibold text-slate-500">{{ $e['label'] }}</div>
                                    <div class="mt-1 text-sm font-semibold {{ $e['muted'] ? 'text-slate-500' : 'text-slate-900' }}">{{ $e['value'] }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-semibold text-slate-900">Talents</div>
                            </div>

                            @php
                                $talents = is_array($astro['talents'] ?? null) ? $astro['talents'] : [];
                                $talents = array_values(array_filter(array_map('strval', $talents)));
                                $visible = array_slice($talents, 0, 6);
                                $more = count($talents) - count($visible);
                            @endphp

                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse($visible as $t)
                                    <span class="inline-flex items-center h-8 px-3 rounded-full text-sm border border-slate-200 bg-white font-semibold text-slate-700">{{ $t }}</span>
                                @empty
                                    <span class="text-sm text-slate-500">À calculer</span>
                                @endforelse

                                @if($more > 0)
                                    <button type="button" @click="openTalents = true" class="inline-flex items-center h-8 px-3 rounded-full text-sm border border-slate-200 bg-white font-semibold text-slate-700 hover:bg-slate-50">
                                        Voir tout
                                    </button>
                                @endif
                            </div>
                        </div>

                        @php
                            $vigilance = trim((string) ($astro['vigilance'] ?? ''));
                        @endphp

                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 text-amber-900 text-xs">
                                    <i class="ph ph-warning-circle" aria-hidden="true"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-amber-950/80">Point de vigilance</div>
                                    <div class="mt-1 text-sm font-medium text-amber-950">{{ $vigilance !== '' ? $vigilance : 'À calculer' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($tab === 'chart')
                <div x-show="show" x-transition.opacity.duration.180ms x-transition.transform.duration.180ms class="bg-white shadow sm:rounded-2xl">
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Carte du ciel</div>

                        @if(($astro['precision'] ?? 'unknown') === 'unknown')
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="text-sm font-semibold text-amber-950">Ajoute l’heure et le lieu pour calculer l’Ascendant & les maisons</div>
                                <div class="mt-1 text-sm text-amber-950/80">Ensuite, la carte complète apparaîtra ici.</div>
                                <div class="mt-3">
                                    <a href="{{ $birthCtaUrl }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 active:bg-amber-800">Compléter</a>
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
                                <div class="text-sm font-semibold text-slate-900">Carte du ciel</div>
                                <div class="mt-1 text-sm text-slate-600">Elle s’affichera ici quand tout est prêt.</div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div x-show="show" x-transition.opacity.duration.180ms x-transition.transform.duration.180ms class="bg-white shadow sm:rounded-2xl">
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Lieux</div>

                        @php
                            $place = trim((string) ($user->birth_place ?? ''));
                            $time = trim((string) ($user->birth_time ?? ''));
                            $tz = 'Europe/Paris';
                        @endphp

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-xs text-slate-500">Naissance</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $place !== '' ? $place : 'À compléter' }}</div>
                            <div class="mt-1 text-sm text-slate-600">
                                Heure locale: {{ $time !== '' ? $time : 'À compléter' }} · Fuseau: {{ $tz }}
                            </div>
                            <div class="mt-3">
                                <a href="{{ $birthCtaUrl }}" class="inline-flex items-center h-10 px-4 rounded-md border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">Corriger / préciser</a>
                            </div>
                        </div>

                        <div class="text-xs text-slate-500">Les coordonnées précises restent masquées (MVP).</div>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-sm font-semibold text-slate-900">Actions</div>
                    <div class="mt-3 grid sm:grid-cols-2 gap-3">
                        <a href="{{ $birthCtaUrl }}" class="inline-flex items-center justify-center h-11 px-4 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm font-semibold hover:bg-slate-50">
                            Modifier mes infos de naissance
                        </a>

                        <form method="POST" action="{{ route('avatar.astro.generate') }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center h-11 px-4 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 active:bg-slate-950">
                                Générer mon avatar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Talents bottom sheet -->
            @php
                $allTalents = is_array($astro['talents'] ?? null) ? $astro['talents'] : [];
                $allTalents = array_values(array_filter(array_map('strval', $allTalents)));
            @endphp
            @if(count($allTalents) > 0)
                <div x-show="openTalents" x-cloak class="fixed inset-0 z-50" aria-modal="true" role="dialog">
                    <button type="button" @click="openTalents = false" class="absolute inset-0 bg-black/30" aria-label="Fermer"></button>

                    <div
                        x-show="openTalents"
                        x-transition.opacity.duration.160ms
                        x-transition.transform.duration.160ms
                        class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-white p-4 shadow-2xl"
                        style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem)"
                    >
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-slate-900">Tous mes talents</div>
                            <button type="button" @click="openTalents = false" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Fermer</button>
                        </div>

                        <div class="mt-3 max-h-[55vh] overflow-y-auto">
                            <div class="flex flex-wrap gap-2">
                                @foreach($allTalents as $t)
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ $t }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
