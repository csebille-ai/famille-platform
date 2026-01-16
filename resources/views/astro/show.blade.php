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

        $lifePathHero = (int) ($astro['life_path'] ?? 0);
        $chineseHero = trim((string) ($astro['chinese'] ?? ''));

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

        $elementHero = $sun !== '' ? $elementFromSign($sun) : '';
        $signatureParts = [];
        if ($lifePathHero > 0) $signatureParts[] = (string) $lifePathHero;
        if ($chineseHero !== '') $signatureParts[] = $chineseHero;
        $signatureLine = implode(' • ', $signatureParts);

        $birthCtaUrl = isset($birthCtaUrl)
            ? (string) $birthCtaUrl
            : (route('profile.edit') . '#astro-birth');

        $isChartMissing = $precision !== 'exact' || $moon === '' || $asc === '';
        $themeForSign = function (string $sign, string $variant = 'sun'): array {
            $sign = mb_strtolower(trim($sign));

            $base = [
                'box' => 'border-slate-200 bg-white',
                'label' => 'text-slate-500',
                'value' => 'text-slate-900',
            ];

            $themes = [
                'bélier' => [
                    'sun' => ['box' => 'border-rose-200/80 bg-rose-50/80', 'label' => 'text-rose-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-rose-200/60 bg-rose-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'taureau' => [
                    'sun' => ['box' => 'border-emerald-200/80 bg-emerald-50/70', 'label' => 'text-emerald-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-emerald-200/60 bg-emerald-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'gémeaux' => [
                    'sun' => ['box' => 'border-sky-200/80 bg-sky-50/80', 'label' => 'text-sky-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-sky-200/60 bg-sky-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'cancer' => [
                    'sun' => ['box' => 'border-indigo-200/80 bg-indigo-50/70', 'label' => 'text-indigo-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-indigo-200/60 bg-indigo-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'lion' => [
                    'sun' => ['box' => 'border-amber-200/80 bg-amber-50/70', 'label' => 'text-amber-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-amber-200/60 bg-amber-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'vierge' => [
                    'sun' => ['box' => 'border-teal-200/80 bg-teal-50/70', 'label' => 'text-teal-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-teal-200/60 bg-teal-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'balance' => [
                    'sun' => ['box' => 'border-violet-200/80 bg-violet-50/70', 'label' => 'text-violet-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-violet-200/60 bg-violet-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'scorpion' => [
                    'sun' => ['box' => 'border-fuchsia-200/80 bg-fuchsia-50/70', 'label' => 'text-fuchsia-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-fuchsia-200/60 bg-fuchsia-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'sagittaire' => [
                    'sun' => ['box' => 'border-orange-200/80 bg-orange-50/70', 'label' => 'text-orange-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-orange-200/60 bg-orange-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'capricorne' => [
                    'sun' => ['box' => 'border-stone-200/80 bg-stone-50/80', 'label' => 'text-stone-700', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-stone-200/60 bg-stone-50/50', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'verseau' => [
                    'sun' => ['box' => 'border-cyan-200/80 bg-cyan-50/70', 'label' => 'text-cyan-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-cyan-200/60 bg-cyan-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
                'poissons' => [
                    'sun' => ['box' => 'border-blue-200/80 bg-blue-50/70', 'label' => 'text-blue-900/70', 'value' => 'text-slate-900'],
                    'soft' => ['box' => 'border-blue-200/60 bg-blue-50/40', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
                ],
            ];

            $t = $themes[$sign][$variant] ?? null;
            if (!$t) return $base;

            return [
                'box' => $t['box'] ?? $base['box'],
                'label' => $t['label'] ?? $base['label'],
                'value' => $t['value'] ?? $base['value'],
            ];
        };

        $pill = function (string $label, string $value, array $opts = []) use ($themeForSign) {
            $raw = trim($value);
            $value = trim($value);
            $isMissing = ($value === '');

            $missingValue = (string) ($opts['missingValue'] ?? '—');
            $hintWhenMissing = (string) ($opts['hintWhenMissing'] ?? '');
            $variant = (string) ($opts['variant'] ?? 'soft');

            $theme = $isMissing
                ? ['box' => 'border-slate-200 bg-white', 'label' => 'text-slate-500', 'value' => 'text-slate-400']
                : $themeForSign($raw, $variant);

            return [
                'label' => $label,
                'value' => $isMissing ? $missingValue : $value,
                'missing' => $isMissing,
                'hint' => $isMissing ? $hintWhenMissing : '',
                'boxClass' => $theme['box'],
                'labelClass' => $theme['label'],
                'valueClass' => $theme['value'],
            ];
        };

        $pills = [
            $pill('Soleil', $sun, ['missingValue' => '—', 'variant' => 'sun']),
            $pill('Lune', $moon, ['missingValue' => 'Non dispo', 'hintWhenMissing' => 'Heure/lieu requis', 'variant' => 'soft']),
            $pill('Ascendant', $asc, ['missingValue' => '—', 'variant' => 'soft']),
        ];

        $tabs = [
            'profile' => 'Synthèse',
            'chart' => 'Carte du ciel',
            'places' => 'Naissance',
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

                            {{-- signature moved under zodiac frames --}}
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
                        <div class="rounded-2xl border p-3 min-h-[74px] {{ $p['boxClass'] }}">
                            <div class="text-[11px] font-semibold {{ $p['labelClass'] }}">{{ $p['label'] }}</div>
                            <div class="mt-1 text-lg font-extrabold tracking-tight {{ $p['missing'] ? 'text-slate-400' : $p['valueClass'] }}">
                                {{ $p['value'] }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-400 leading-4">
                                {{ $p['hint'] !== '' ? $p['hint'] : ' ' }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($signatureLine !== '' || $elementHero !== '')
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if($elementHero !== '')
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-slate-200/70 bg-slate-50 text-[11px] font-semibold text-slate-700">
                                Élément: {{ $elementHero }}
                            </span>
                        @endif
                        @if($signatureLine !== '')
                            <div class="text-xs text-slate-600">{{ $signatureLine }}</div>
                        @endif
                    </div>
                @endif

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
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Essentiel</div>

                        @php
                            $chinese = trim((string) ($astro['chinese'] ?? ''));
                            $lifePath = (int) ($astro['life_path'] ?? 0);

                            $essentialCards = [
                                [
                                    'label' => 'Signe chinois',
                                    'value' => $chinese !== '' ? $chinese : 'À compléter',
                                    'muted' => $chinese === '',
                                ],
                                [
                                    'label' => 'Numérologie',
                                    'value' => $lifePath > 0 ? (string) $lifePath : 'À calculer',
                                    'muted' => $lifePath <= 0,
                                ],
                            ];
                        @endphp

                        <div class="grid gap-2 min-[420px]:grid-cols-2 sm:gap-3">
                            @foreach($essentialCards as $e)
                                @php
                                    $accent = $loop->first ? 'bg-sky-400/70' : 'bg-violet-400/70';
                                @endphp
                                <div class="h-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $accent }}"></span>
                                        <div class="text-[11px] font-semibold text-slate-500">{{ $e['label'] }}</div>
                                    </div>
                                    <div class="mt-1 text-sm font-semibold {{ $e['muted'] ? 'text-slate-500' : 'text-slate-900' }}">{{ $e['value'] }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-1">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-semibold text-slate-900">Talents</div>
                            </div>

                            @php
                                $talents = is_array($astro['talents'] ?? null) ? $astro['talents'] : [];
                                $talents = array_values(array_filter(array_map('strval', $talents)));
                                $visible = array_slice($talents, 0, 3);
                                $more = count($talents) - count($visible);
                            @endphp

                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse($visible as $t)
                                    <span class="inline-flex items-center h-8 px-3 rounded-full text-sm border border-slate-200 bg-white font-semibold text-slate-700">{{ $t }}</span>
                                @empty
                                    <span class="text-sm text-slate-500">À calculer</span>
                                @endforelse

                                @if($more > 0)
                                    <button type="button" @click="openTalents = true" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm border border-slate-200 bg-white font-semibold text-slate-700 hover:bg-slate-50">
                                        <span>Voir +</span>
                                        <i class="ph ph-caret-down text-slate-500" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </div>
                        </div>

                        @php
                            $vigilance = trim((string) ($astro['vigilance'] ?? ''));

                            $strengthFromSun = function (string $sign): string {
                                $sign = mb_strtolower(trim($sign));

                                $map = [
                                    'bélier' => 'Courage & initiative',
                                    'taureau' => 'Stabilité & persévérance',
                                    'gémeaux' => 'Curiosité & adaptabilité',
                                    'cancer' => 'Protection & sensibilité',
                                    'lion' => 'Rayonnement & créativité',
                                    'vierge' => 'Analyse & précision',
                                    'balance' => 'Diplomatie & sens de l’équilibre',
                                    'scorpion' => 'Profondeur & détermination',
                                    'sagittaire' => 'Optimisme & exploration',
                                    'capricorne' => 'Discipline & ambition',
                                    'verseau' => 'Innovation & indépendance',
                                    'poissons' => 'Intuition & imagination',
                                ];

                                return (string) ($map[$sign] ?? '');
                            };

                            $strength = trim((string) ($astro['strength'] ?? ''));
                            if ($strength === '' && $sun !== '') {
                                $strength = $strengthFromSun($sun);
                            }
                            if ($strength === '' && $archetypeHero !== '') {
                                $strength = 'Clé: ' . $archetypeHero;
                            }
                            if ($strength === '') {
                                $strength = trim((string) ($talents[0] ?? ''));
                            }
                        @endphp

                        <div class="mt-1">
                            <div class="text-sm font-semibold text-slate-900">Insights</div>
                            <div class="mt-2 grid gap-2 min-[420px]:grid-cols-2 sm:gap-3">
                                <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="text-[11px] font-semibold text-slate-600">Point fort</div>
                                    <div class="mt-1 text-sm font-semibold text-emerald-950">{{ $strength !== '' ? $strength : '—' }}</div>
                                </div>
                                <div class="rounded-2xl border border-amber-200/70 bg-amber-50/60 px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="text-[11px] font-semibold text-slate-600">À surveiller</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $vigilance !== '' ? $vigilance : 'À calculer' }}</div>
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
                        <a href="{{ $birthCtaUrl }}" class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm font-semibold hover:bg-slate-50">
                            Modifier mes infos de naissance
                        </a>

                        <form method="POST" action="{{ route('avatar.astro.generate') }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center h-10 px-4 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 active:bg-slate-950">
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
