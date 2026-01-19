@php
    /** @var \App\Services\Ephemeris\EphemerisService $svc */
    $svc = app(\App\Services\Ephemeris\EphemerisService::class);
    $e = $svc->today();
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-2xl mx-auto px-6 py-6 space-y-4">
        <div class="fam-card p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[color:var(--fam-muted)]">Éphéméride</div>
                    <div class="mt-1 text-xl font-semibold text-[color:var(--fam-text)] truncate">
                        {{ $e['date_label'] ?? '—' }}
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full border border-[color:rgba(14,165,160,0.14)] bg-[color:var(--fam-tint)] px-3 py-1 text-sm font-semibold text-[color:var(--fam-primary-hover)]">
                        <i class="ph ph-sun text-[18px]" aria-hidden="true"></i>
                        <span>{{ $e['sunrise_time'] ?? '' }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full border border-[color:rgba(14,165,160,0.14)] bg-[color:var(--fam-tint)] px-3 py-1 text-sm font-semibold text-[color:var(--fam-primary-hover)]">
                        <i class="ph ph-moon text-[18px]" aria-hidden="true"></i>
                        <span>{{ $e['sunset_time'] ?? '' }}</span>
                    </span>
                </div>
            </div>

            <div class="mt-5 fam-card-soft p-4">
                <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $e['saint_name'] ?? '—' }}</div>
                <blockquote class="mt-2 border-s-2 border-[color:rgba(14,165,160,0.30)] ps-3">
                    <div class="text-[15px] leading-snug text-[color:var(--fam-muted)]">
                        {{ $e['proverb_text'] ?? '' }}
                    </div>
                </blockquote>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <a href="{{ route('dashboard') }}" class="fam-link">Retour à l’accueil</a>
            </div>
        </div>
    </div>
</x-app-layout>
