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

        $chineseHero = trim((string) ($astro['chinese'] ?? ''));
        $kemeticIndexHero = (int) ($astro['kemetic_decan_index'] ?? 0);
        $kemeticLabelHero = trim((string) ($astro['kemetic_decan_label'] ?? ''));
        $kemeticNameHero = $kemeticIndexHero > 0
            ? \App\Services\Astro\Kemetic\KemeticDecan::nameFromIndex($kemeticIndexHero)
            : '';

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
        if ($chineseHero !== '') $signatureParts[] = $chineseHero;
        if ($kemeticNameHero !== '') $signatureParts[] = $kemeticNameHero;
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
        ];
    @endphp

    <div x-data="{ show: false, openTalents: false, openPlanet: false, planet: null }" x-init="requestAnimationFrame(() => show = true)" class="pt-[calc(env(safe-area-inset-top)+0.75rem)] pb-[calc(env(safe-area-inset-bottom)+1.5rem)]">
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
                            $kemeticIndex = (int) ($astro['kemetic_decan_index'] ?? 0);
                            $kemeticName = $kemeticIndex > 0
                                ? \App\Services\Astro\Kemetic\KemeticDecan::nameFromIndex($kemeticIndex)
                                : '';

                            $essentialCards = [
                                [
                                    'label' => 'Signe chinois',
                                    'value' => $chinese !== '' ? $chinese : 'À compléter',
                                    'muted' => $chinese === '',
                                    'meta' => '',
                                ],
                                [
                                    'label' => 'Zodiac kémétique',
                                    'value' => $kemeticName !== '' ? $kemeticName : 'À calculer',
                                    'muted' => $kemeticName === '' && $kemeticIndex <= 0,
                                    'meta' => '',
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
                                    @if(($e['meta'] ?? '') !== '')
                                        <div class="mt-0.5 text-[11px] text-slate-500">{{ $e['meta'] }}</div>
                                    @endif
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

                        <div class="pt-2">
                            <div class="text-sm font-semibold text-slate-900">Actions</div>
                            <div class="mt-3 grid sm:grid-cols-2 gap-3">
                                <a href="{{ $birthCtaUrl }}" class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm font-semibold hover:bg-slate-50">
                                    Modifier mes infos
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
                </div>
            @elseif($tab === 'chart')
                <div x-show="show" x-transition.opacity.duration.180ms x-transition.transform.duration.180ms class="bg-white shadow sm:rounded-2xl">
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Carte du ciel</div>

                        @php
                            $birthTimeLocal = trim((string) ($user->birth_time ?? ''));
                            $hasCoords = $user->birth_latitude !== null && $user->birth_longitude !== null;
                            $missingTime = $birthTimeLocal === '';
                            $missingCoords = !$hasCoords;

                            $natal = is_array($astro['natal'] ?? null) ? (array) $astro['natal'] : null;
                            $angles = is_array($natal['angles'] ?? null) ? (array) $natal['angles'] : [];
                            $houses = is_array($natal['houses'] ?? null) ? (array) $natal['houses'] : [];
                            $planets = is_array($natal['planets'] ?? null) ? (array) $natal['planets'] : [];

                            $fmtDeg = function ($deg) {
                                $deg = is_numeric($deg) ? (float) $deg : 0.0;
                                $d = (int) floor($deg);
                                $m = (int) round(($deg - $d) * 60);
                                if ($m >= 60) {
                                    $d += 1;
                                    $m = 0;
                                }
                                $d = $d % 30;
                                return sprintf('%d°%02d', $d, $m);
                            };

                            $wheelXY = function ($deg, $r) {
                                $deg = is_numeric($deg) ? (float) $deg : 0.0;
                                $rad = deg2rad($deg - 90.0);
                                return [
                                    'x' => $r * cos($rad),
                                    'y' => $r * sin($rad),
                                ];
                            };

                            $planetGlyph = function (string $key): string {
                                return match ($key) {
                                    'sun' => '☉',
                                    'moon' => '☽',
                                    'mercury' => '☿',
                                    'venus' => '♀',
                                    'mars' => '♂',
                                    default => '•',
                                };
                            };

                            $planetRows = [];
                            foreach ($planets as $pl) {
                                if (!is_array($pl)) continue;
                                $key = (string) ($pl['key'] ?? '');
                                $name = (string) ($pl['name'] ?? '');
                                $sign = (string) ($pl['sign'] ?? '');
                                $degInSign = $pl['deg_in_sign'] ?? null;
                                $house = (int) ($pl['house'] ?? 0);
                                $lon = $pl['lon'] ?? null;
                                $planetRows[] = [
                                    'key' => $key,
                                    'glyph' => $planetGlyph($key),
                                    'name' => $name,
                                    'sign' => $sign,
                                    'deg_in_sign' => is_numeric($degInSign) ? (float) $degInSign : null,
                                    'deg_label' => $fmtDeg($degInSign),
                                    'house' => $house > 0 ? $house : null,
                                    'lon' => is_numeric($lon) ? (float) $lon : null,
                                ];
                            }
                        @endphp

                        @if($missingCoords)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="text-sm font-semibold text-amber-950">Ajoute le lieu précis pour calculer Ascendant & maisons.</div>
                                <div class="mt-3">
                                    <a href="{{ $birthCtaUrl }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 active:bg-amber-800">Modifier</a>
                                </div>
                            </div>
                        @elseif($missingTime)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="text-sm font-semibold text-amber-950">Ajoute l’heure pour calculer la carte.</div>
                                <div class="mt-3">
                                    <a href="{{ $birthCtaUrl }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 active:bg-amber-800">Modifier</a>
                                </div>
                            </div>
                        @elseif(!is_array($natal) || empty($planetRows))
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-sm font-semibold text-slate-900">La carte n’est pas disponible pour le moment.</div>
                                <div class="mt-3">
                                    <a href="{{ $birthCtaUrl }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-slate-200 bg-white text-slate-800 text-sm font-semibold hover:bg-slate-50">Modifier</a>
                                </div>
                            </div>
                        @else
                            @php
                                $asc = is_array($angles['asc'] ?? null) ? (array) $angles['asc'] : [];
                                $mc = is_array($angles['mc'] ?? null) ? (array) $angles['mc'] : [];
                                $ascSign = (string) ($asc['sign'] ?? '');
                                $ascDeg = $asc['deg_in_sign'] ?? null;
                                $mcSign = (string) ($mc['sign'] ?? '');
                                $mcDeg = $mc['deg_in_sign'] ?? null;
                            @endphp

                            <div class="grid gap-4 sm:grid-cols-[minmax(0,360px)_1fr]">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm font-semibold text-slate-900">Roue</div>
                                        <div class="text-xs text-slate-500">
                                            <span class="font-semibold text-slate-700">ASC</span>
                                            {{ $ascSign !== '' ? ($ascSign . ' ' . $fmtDeg($ascDeg)) : '—' }}
                                            <span class="mx-1">·</span>
                                            <span class="font-semibold text-slate-700">MC</span>
                                            {{ $mcSign !== '' ? ($mcSign . ' ' . $fmtDeg($mcDeg)) : '—' }}
                                        </div>
                                    </div>

                                    <div class="mt-3 aspect-square">
                                        <svg viewBox="-120 -120 240 240" class="h-full w-full">
                                            <circle cx="0" cy="0" r="108" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" />
                                            <circle cx="0" cy="0" r="78" fill="#ffffff" stroke="#e2e8f0" stroke-width="2" />
                                            <circle cx="0" cy="0" r="36" fill="#ffffff" stroke="#e2e8f0" stroke-width="2" />

                                            @foreach($houses as $h)
                                                @php
                                                    if (!is_array($h)) continue;
                                                    $cuspLon = $h['cusp_lon'] ?? null;
                                                    $p = $wheelXY($cuspLon, 108);
                                                @endphp
                                                <line x1="0" y1="0" x2="{{ $p['x'] }}" y2="{{ $p['y'] }}" stroke="#cbd5e1" stroke-width="1" />
                                            @endforeach

                                            @foreach($planetRows as $pl)
                                                @php
                                                    $lon = $pl['lon'] ?? null;
                                                    $pt = $wheelXY($lon, 66);
                                                @endphp
                                                <text x="{{ $pt['x'] }}" y="{{ $pt['y'] }}" text-anchor="middle" dominant-baseline="middle" font-size="14" fill="#0f172a">{{ $pl['glyph'] }}</text>
                                            @endforeach

                                            <text x="0" y="0" text-anchor="middle" dominant-baseline="middle" font-size="10" fill="#64748b">Carte</text>
                                        </svg>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="text-sm font-semibold text-slate-900">Planètes</div>
                                    <div class="mt-3 divide-y divide-slate-100">
                                        @foreach($planetRows as $pl)
                                            <button
                                                type="button"
                                                class="w-full py-3 flex items-center justify-between gap-3 text-left"
                                                @click="planet = {{ \Illuminate\Support\Js::from($pl) }}; openPlanet = true"
                                            >
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-base">{{ $pl['glyph'] }}</span>
                                                        <div class="text-sm font-semibold text-slate-900">{{ $pl['name'] }}</div>
                                                    </div>
                                                    <div class="mt-0.5 text-xs text-slate-600">
                                                        {{ ($pl['sign'] ?? '') !== '' ? ($pl['sign'] . ' ' . ($pl['deg_label'] ?? '')) : '—' }}
                                                        @if(($pl['house'] ?? null) !== null)
                                                            <span class="mx-1">·</span>
                                                            Maison {{ $pl['house'] }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <i class="ph ph-caret-right text-slate-400" aria-hidden="true"></i>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div x-show="openPlanet" x-cloak class="fixed inset-0 z-50" aria-modal="true" role="dialog">
                                <button type="button" @click="openPlanet = false" class="absolute inset-0 bg-black/30" aria-label="Fermer"></button>

                                <div
                                    x-show="openPlanet"
                                    x-transition.opacity.duration.160ms
                                    x-transition.transform.duration.160ms
                                    class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-white p-4 shadow-2xl"
                                    style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem)"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm font-semibold text-slate-900" x-text="planet ? (planet.glyph + ' ' + planet.name) : ''"></div>
                                        <button type="button" @click="openPlanet = false" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Fermer</button>
                                    </div>

                                    <div class="mt-3 grid gap-2">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                            <div class="text-[11px] font-semibold text-slate-500">Position</div>
                                            <div class="mt-1 text-sm font-semibold text-slate-900" x-text="planet && planet.sign ? (planet.sign + ' ' + planet.deg_label) : '—'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                            <div class="text-[11px] font-semibold text-slate-500">Maison</div>
                                            <div class="mt-1 text-sm font-semibold text-slate-900" x-text="planet && planet.house ? ('Maison ' + planet.house) : '—'"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

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
