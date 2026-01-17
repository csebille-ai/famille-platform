<x-app-layout pageBgClass="fam-page-bg">
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $events */
        $scope = $scope ?? 'upcoming';
        $filter = $filter ?? 'all';

        $scopeLabels = [
            'upcoming' => 'À venir',
            'past' => 'Passés',
        ];

        $filterLabels = [
            'all' => 'Tous',
            'important' => 'Importants',
            'family' => 'Famille',
            'personal' => 'Perso',
            'mine' => 'Mes événements',
        ];

        $badgeForCategory = function (?string $cat): array {
            $cat = strtolower(trim((string) $cat));
            return match ($cat) {
                'family' => ['label' => 'Famille', 'icon' => 'ph-users'],
                'personal' => ['label' => 'Perso', 'icon' => 'ph-user'],
                'school' => ['label' => 'École', 'icon' => 'ph-graduation-cap'],
                'travel' => ['label' => 'Voyage', 'icon' => 'ph-airplane'],
                'medical' => ['label' => 'Médical', 'icon' => 'ph-first-aid'],
                'admin' => ['label' => 'Admin', 'icon' => 'ph-shield'],
                default => ['label' => 'Autre', 'icon' => 'ph-star'],
            };
        };

        $fmtDay = function ($dt): string {
            try { return $dt?->translatedFormat('d'); } catch (Throwable) { return ''; }
        };
        $fmtMonth = function ($dt): string {
            try { return mb_strtoupper((string) $dt?->translatedFormat('M')); } catch (Throwable) { return ''; }
        };
        $fmtWhen = function ($e): string {
            $dt = $e->start_at;
            if (!$dt) return '—';
            try {
                if ($e->all_day) return $dt->translatedFormat('EEE d MMM') . ' • Toute la journée';
                return $dt->translatedFormat('EEE d MMM') . ' • ' . $dt->format('H:i');
            } catch (Throwable) {
                return '—';
            }
        };
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-base font-semibold text-[color:var(--fam-text)]">Événements</div>
                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Agenda de la famille</div>
            </div>
            <a href="{{ route('events.create') }}" class="shrink-0 inline-flex items-center gap-2 h-10 px-4 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold shadow-sm hover:bg-[color:var(--fam-primary-hover)] active:scale-[0.99]">
                <i class="ph ph-plus" aria-hidden="true"></i>
                Ajouter
            </a>
        </div>

        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="inline-flex bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] rounded-2xl p-1">
                    @foreach(['upcoming','past'] as $k)
                        <a
                            href="{{ route('events.index', ['scope' => $k, 'filter' => $filter]) }}"
                            class="px-4 text-center h-9 inline-flex items-center justify-center rounded-2xl text-sm font-semibold transition-all duration-150 {{ $scope === $k ? 'bg-white text-[color:var(--fam-text)] shadow-sm' : 'text-[color:var(--fam-muted)] hover:text-[color:var(--fam-text)]' }}"
                        >
                            {{ $scopeLabels[$k] ?? $k }}
                        </a>
                    @endforeach
                </div>

                <div class="shrink-0">
                    <select
                        onchange="window.location.href=this.value"
                        class="h-10 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25"
                        aria-label="Filtrer"
                    >
                        @foreach($filterLabels as $k => $label)
                            <option value="{{ route('events.index', ['scope' => $scope, 'filter' => $k]) }}" {{ $filter === $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 space-y-2">
                @if($events->count())
                    @foreach($events as $e)
                        @php
                            $cat = $badgeForCategory($e->category);
                            $isPrivate = ($e->visibility ?? 'family') === 'private';
                        @endphp
                        <a href="{{ route('events.show', $e) }}" class="block">
                            <div class="rounded-2xl bg-white border border-[color:var(--fam-border-soft)] px-3 py-2.5 hover:shadow-sm transition active:scale-[0.995]">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0 rounded-2xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] px-2.5 py-2 text-center">
                                        <div class="text-base font-extrabold text-[color:var(--fam-text)] leading-none">{{ $fmtDay($e->start_at) }}</div>
                                        <div class="mt-0.5 text-[0.65rem] font-extrabold text-[color:var(--fam-muted)] leading-none tracking-wide">{{ $fmtMonth($e->start_at) }}</div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $e->title }}</div>
                                                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">{{ $fmtWhen($e) }}</div>
                                            </div>

                                            <div class="shrink-0 flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1 rounded-full border border-[color:var(--fam-border-soft)] bg-white px-2 py-1 text-[0.7rem] font-extrabold text-[color:var(--fam-muted)]">
                                                    <i class="ph {{ $cat['icon'] }}" aria-hidden="true"></i>
                                                    <span>{{ $cat['label'] }}</span>
                                                </span>

                                                @if($e->is_important)
                                                    <span class="inline-flex items-center rounded-full bg-[color:var(--fam-tint)] px-2 py-1 text-[0.7rem] font-extrabold text-[color:var(--fam-primary-hover)] border border-[color:rgba(14,165,160,0.18)]">Important</span>
                                                @endif

                                                @if($isPrivate)
                                                    <span class="inline-flex items-center rounded-full border border-[color:var(--fam-border-soft)] bg-white px-2 py-1 text-[0.7rem] font-extrabold text-[color:var(--fam-muted)]" title="Privé">
                                                        <i class="ph ph-lock" aria-hidden="true"></i>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach

                    <div class="pt-2">{{ $events->links() }}</div>
                @else
                    <div class="rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-4 py-6 text-sm text-[color:var(--fam-muted)]">
                        Rien à afficher pour l’instant.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
