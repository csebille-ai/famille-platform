@php
    /** @var \App\Services\Ephemeris\EphemerisService $svc */
    $svc = app(\App\Services\Ephemeris\EphemerisService::class);
    $e = $svc->today();
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-2xl mx-auto px-6 py-6 space-y-4">
        <div class="fam-card p-5">
            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Éphéméride</div>

            <div class="mt-3 text-sm text-[color:var(--fam-muted)] flex items-center gap-3">
                <div class="font-semibold text-[color:var(--fam-text)]">{{ $e['date_label'] ?? '—' }}</div>
                <div class="inline-flex items-center gap-1">
                    <i class="ph ph-sun" aria-hidden="true"></i>
                    <span>{{ $e['sunrise_time'] ?? '' }}</span>
                </div>
                <div class="inline-flex items-center gap-1">
                    <i class="ph ph-moon" aria-hidden="true"></i>
                    <span>{{ $e['sunset_time'] ?? '' }}</span>
                </div>
            </div>

            <div class="mt-3 text-base font-semibold text-[color:var(--fam-text)]">{{ $e['saint_name'] ?? '—' }}</div>
            <div class="mt-2 text-sm text-[color:var(--fam-muted)]">{{ $e['proverb_text'] ?? '' }}</div>
        </div>
    </div>
</x-app-layout>
