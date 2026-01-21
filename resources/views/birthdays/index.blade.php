@php
    /** @var array<int,array{name:string, initials:string, avatar_url?:string|null, next_date:\Carbon\CarbonImmutable, days_remaining:int, turning_age:int|null}> $birthdays */
    $birthdays = $birthdays ?? [];
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-3xl mx-auto px-6 py-6 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-gray-900">Anniversaires</h1>
            <a href="{{ route('dashboard') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-gray-900">Retour</a>
        </div>

        @if (count($birthdays) === 0)
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-base font-semibold text-gray-900">Aucune date de naissance</div>
                <div class="mt-1 text-sm text-slate-600">Ajoutez des dates de naissance sur les profils.</div>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200 bg-white divide-y">
                @foreach($birthdays as $b)
                    @php
                        $days = (int) ($b['days_remaining'] ?? 0);
                        $date = $b['next_date'] ?? null;
                        $labelDate = $date ? $date->locale(app()->getLocale())->translatedFormat('d M') : '';
                        $age = $b['turning_age'] ?? null;
                        $birthdayAvatarUrl = $b['avatar_url'] ?? null;
                    @endphp

                    <div class="p-4 flex items-center gap-3">
                        @if ($birthdayAvatarUrl)
                            <img
                                src="{{ $birthdayAvatarUrl }}"
                                alt=""
                                class="h-10 w-10 flex-shrink-0 rounded-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        @else
                            <div class="h-10 w-10 rounded-full bg-slate-900 text-white flex items-center justify-center text-sm font-bold">
                                {{ $b['initials'] ?? '?' }}
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-gray-900 truncate">{{ $b['name'] ?? '—' }}</div>
                            <div class="text-sm text-slate-600">
                                @if($days === 0)
                                    🎉 Aujourd’hui
                                @else
                                    {{ $labelDate }} · dans {{ $days }} jour{{ $days > 1 ? 's' : '' }}
                                @endif
                                @if(is_int($age))
                                    <span class="text-slate-400">·</span> {{ $age }} ans
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="text-sm font-semibold text-gray-900">{{ $labelDate }}</div>
                            <div class="text-xs text-slate-500">J-{{ $days }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
