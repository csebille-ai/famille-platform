<x-app-layout>
    @php
        /** @var \App\Models\User $user */
        $displayName = trim((string) ($user->name ?? ''));
        $dob = $user->date_of_birth;
        $age = null;
        if ($dob) {
            try {
                $age = \Carbon\CarbonImmutable::instance($dob)->diffInYears(\Carbon\CarbonImmutable::now('Europe/Paris'));
            } catch (\Throwable) {
                $age = null;
            }
        }

        $hasAvatar = trim((string) ($user->avatar_image_url ?? '')) !== '';
        $avatarV = optional($user->avatar_updated_at)->getTimestamp() ?? time();

        $sun = trim((string) ($astro['sun_sign'] ?? ''));
        $moon = trim((string) ($astro['moon_sign'] ?? ''));
        $asc = trim((string) ($astro['ascendant'] ?? ''));
        $precision = (string) ($astro['precision'] ?? 'unknown');

        $precLabel = match ($precision) {
            'exact' => 'Données exactes',
            'approx' => 'Données approx.',
            default => 'Données inconnues',
        };

        $precClass = match ($precision) {
            'exact' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'approx' => 'bg-amber-50 text-amber-900 border-amber-200',
            default => 'bg-slate-50 text-slate-600 border-slate-200',
        };

        $pill = function (string $label, string $value) {
            $value = trim($value);
            return [
                'label' => $label,
                'value' => $value !== '' ? $value : '—',
                'muted' => $value === '',
            ];
        };

        $pills = [
            $pill('Soleil', $sun),
            $pill('Lune', $moon),
            $pill('Ascendant', $asc),
        ];

        $tabs = [
            'profile' => 'Profil',
            'chart' => 'Carte',
            'places' => 'Lieux',
        ];
    @endphp

    <div class="pt-3 pb-12 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-0">
                <a href="{{ url()->previous() }}" class="inline-flex items-center h-10 px-4 rounded-md border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">
                    Retour
                </a>

                <div class="text-right">
                    <div class="text-lg font-semibold text-slate-900">Fiche astro</div>
                    <div class="mt-0.5 text-xs text-slate-500">Lecture</div>
                </div>

                <a href="{{ route('profile.edit') }}#astro-birth" class="inline-flex items-center h-10 px-4 rounded-md border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">
                    Modifier
                </a>
            </div>

            <div class="bg-white shadow sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-start gap-4">
                        <div class="h-14 w-14 rounded-full overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                            @if($hasAvatar)
                                <img src="{{ route('avatar.astro.image', ['v' => $avatarV]) }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer" />
                            @else
                                <div class="text-slate-600 font-semibold">
                                    {{ $user->initials() }}
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-base font-semibold text-slate-900 truncate">{{ $displayName !== '' ? $displayName : '—' }}</div>
                                    <div class="mt-0.5 text-sm text-slate-500">
                                        @if($age !== null)
                                            {{ $age }} ans
                                        @else
                                            Âge —
                                        @endif
                                    </div>
                                </div>

                                <div class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $precClass }}">
                                    {{ $precLabel }}
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($pills as $p)
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div class="text-[11px] text-slate-500">{{ $p['label'] }}</div>
                                        <div class="text-sm font-semibold {{ $p['muted'] ? 'text-slate-400' : 'text-slate-900' }}">{{ $p['value'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="inline-flex w-full rounded-xl border border-slate-200 bg-slate-50 p-1">
                            @foreach($tabs as $key => $label)
                                <a href="{{ route('astro.show', ['tab' => $key]) }}" class="flex-1 text-center rounded-lg px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if($tab === 'profile')
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="p-4 sm:p-6 space-y-5">
                        <div class="text-sm font-semibold text-slate-900">Essentiel</div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="text-xs text-slate-500">Signe chinois</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ trim((string) ($astro['chinese'] ?? '')) !== '' ? $astro['chinese'] : '—' }}</div>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="text-xs text-slate-500">Numérologie</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">
                                    @if((int) ($astro['life_path'] ?? 0) > 0)
                                        Chemin de vie {{ (int) $astro['life_path'] }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="text-xs text-slate-500">Archétype</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ trim((string) ($astro['archetype'] ?? '')) !== '' ? $astro['archetype'] : '—' }}</div>
                            </div>
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
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ $t }}</span>
                                @empty
                                    <span class="text-sm text-slate-500">—</span>
                                @endforelse

                                @if($more > 0)
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">+{{ $more }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs text-slate-500">Point de vigilance</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ trim((string) ($astro['vigilance'] ?? '')) !== '' ? $astro['vigilance'] : '—' }}</div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-sm font-semibold text-slate-900">Avatar astro</div>
                            <div class="mt-1 text-sm text-slate-600">Les trophées et détails arrivent en phase 2.</div>
                        </div>
                    </div>
                </div>
            @elseif($tab === 'chart')
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Carte du ciel</div>

                        @if(($astro['precision'] ?? 'unknown') === 'unknown')
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="text-sm font-semibold text-amber-900">Ajoute l’heure pour calculer l’Ascendant & les maisons</div>
                                <div class="mt-1 text-sm text-amber-900/80">Puis reviens ici pour la carte complète.</div>
                                <div class="mt-3">
                                    <a href="{{ route('profile.edit') }}#astro-birth" class="inline-flex items-center h-10 px-4 rounded-md bg-amber-900 text-white text-sm font-semibold hover:bg-amber-800">Modifier</a>
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
                                <div class="text-sm font-semibold text-slate-900">SVG placeholder (MVP)</div>
                                <div class="mt-1 text-sm text-slate-600">La roue interactive arrive en phase 2.</div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Lieux</div>

                        @php
                            $place = trim((string) ($user->birth_place ?? ''));
                            $time = trim((string) ($user->birth_time ?? ''));
                            $tz = 'Europe/Paris';
                        @endphp

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-xs text-slate-500">Naissance</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $place !== '' ? $place : '—' }}</div>
                            <div class="mt-1 text-sm text-slate-600">
                                Heure locale: {{ $time !== '' ? $time : '—' }} · Fuseau: {{ $tz }}
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('profile.edit') }}#astro-birth" class="inline-flex items-center h-10 px-4 rounded-md border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">Corriger / préciser</a>
                            </div>
                        </div>

                        <div class="text-xs text-slate-500">Les coordonnées précises restent masquées (MVP).</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
