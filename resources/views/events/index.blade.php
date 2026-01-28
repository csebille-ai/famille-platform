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
                $dt = $dt->locale(app()->getLocale());
                if ($e->all_day) return $dt->translatedFormat('EEE d MMM') . ' • Toute la journée';
                $t = $dt->format('H:i');
                if ($t === '00:00') {
                    return $dt->translatedFormat('EEE d MMM');
                }
                return $dt->translatedFormat('EEE d MMM') . ' • ' . $t;
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

        @php
            $hasFamilyCalendar = (bool) ($hasFamilyCalendar ?? false);
            $calendarHttpsUrl = (string) ($calendarHttpsUrl ?? '');
            $calendarWebcalUrl = (string) ($calendarWebcalUrl ?? '');

            $googleConnected = (bool) ($googleConnected ?? false);
            $googleSyncEnabled = (bool) ($googleSyncEnabled ?? false);
        @endphp

        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-extrabold text-[color:var(--fam-text)]">Calendrier Famille pour iPhone</div>
                </div>
                <div class="shrink-0">
                    @if($hasFamilyCalendar)
                        <span class="inline-flex items-center h-8 px-3 rounded-full bg-emerald-50 text-emerald-800 text-xs font-extrabold border border-emerald-200">Abonné</span>
                    @else
                        <span class="inline-flex items-center h-8 px-3 rounded-full bg-slate-50 text-slate-700 text-xs font-extrabold border border-slate-200">Non abonné</span>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                @if($hasFamilyCalendar)
                    <button type="button" id="familyCalendarManageBtn" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">Gérer</button>
                    <form method="POST" action="{{ route('calendar.family.unsubscribe') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl border border-rose-200 bg-rose-50 text-sm font-extrabold text-rose-800 hover:bg-rose-100">Se désabonner</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('calendar.family.subscribe') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">S’abonner</button>
                    </form>
                @endif
            </div>

            @if($hasFamilyCalendar)
                <div id="familyCalendarSheet" class="fixed inset-0 z-50 hidden" aria-hidden="true">
                    <button type="button" id="familyCalendarBackdrop" class="absolute inset-0 bg-black/35"></button>
                    <div class="absolute inset-x-0 bottom-0 flex justify-center">
                        <div class="w-full max-w-[560px] rounded-t-3xl bg-white border border-[color:var(--fam-border-soft)] shadow-[0_-18px_55px_rgba(15,23,42,0.18)] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)]">
                            <div class="mx-auto h-1 w-9 rounded-full bg-black/10"></div>
                            <div class="mt-3 text-sm font-extrabold text-[color:var(--fam-text)]">Calendrier Famille pour iPhone</div>
                            <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">Apple Calendar s’ouvre via webcal. Google/Outlook peuvent utiliser le lien https.</div>
                            <div class="mt-3 grid gap-2">
                                <a href="{{ $calendarWebcalUrl }}" class="w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--fam-border)] bg-white px-4 text-sm font-semibold text-[color:var(--fam-text)] hover:bg-[color:var(--fam-tint)]">
                                    <span>Ouvrir dans Apple Calendar</span>
                                    <i class="ph ph-calendar" aria-hidden="true"></i>
                                </a>
                                <button type="button" id="familyCalendarCopyBtn" class="w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--fam-border)] bg-white px-4 text-sm font-semibold text-[color:var(--fam-text)] hover:bg-[color:var(--fam-tint)]">
                                    <span>Copier le lien d’abonnement</span>
                                    <i class="ph ph-copy" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="familyCalendarCopyStatus" class="mt-2 text-xs font-semibold text-[color:var(--fam-muted)]"></div>
                            <button type="button" id="familyCalendarClose" class="mt-3 w-full h-11 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-white/60 active:bg-white/75">Fermer</button>
                        </div>
                    </div>
                </div>

                <script>
                    (() => {
                        const btn = document.getElementById('familyCalendarManageBtn');
                        const sheet = document.getElementById('familyCalendarSheet');
                        const close = document.getElementById('familyCalendarClose');
                        const backdrop = document.getElementById('familyCalendarBackdrop');
                        const copyBtn = document.getElementById('familyCalendarCopyBtn');
                        const status = document.getElementById('familyCalendarCopyStatus');
                        const httpsUrl = @json($calendarHttpsUrl);
                        if (!btn || !sheet) return;

                        const open = () => {
                            sheet.classList.remove('hidden');
                            sheet.setAttribute('aria-hidden', 'false');
                        };
                        const hide = () => {
                            sheet.classList.add('hidden');
                            sheet.setAttribute('aria-hidden', 'true');
                        };

                        btn.addEventListener('click', open);
                        if (close) close.addEventListener('click', hide);
                        if (backdrop) backdrop.addEventListener('click', hide);

                        if (copyBtn) {
                            copyBtn.addEventListener('click', async () => {
                                try {
                                    await navigator.clipboard.writeText(httpsUrl);
                                    if (status) status.textContent = 'Lien copié.';
                                } catch (e) {
                                    try {
                                        window.prompt('Copier le lien :', httpsUrl);
                                    } catch (e2) {}
                                    if (status) status.textContent = 'Copie manuelle.';
                                }
                            });
                        }
                    })();
                </script>
            @endif
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

                            $cardClasses = $isPrivate
                                ? 'bg-violet-50 border-violet-200'
                                : 'bg-white border-[color:var(--fam-border-soft)]';
                            
                            $households = config('households', []);
                            $household = $e->household_key && isset($households[$e->household_key]) 
                                ? $households[$e->household_key] 
                                : null;
                            $displayLocation = $household 
                                ? ($household['icon'] ?? '🏠') . ' ' . $household['label']
                                : ($e->location_label ?? $e->location);
                        @endphp
                        <a href="{{ route('events.show', $e) }}" class="block">
                            <div class="rounded-2xl border px-3 py-2.5 hover:shadow-sm transition active:scale-[0.995] {{ $cardClasses }}">
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
                                                @if($displayLocation)
                                                    <div class="mt-0.5 text-xs text-[color:var(--fam-muted)] truncate">📍 {{ $displayLocation }}</div>
                                                @endif
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
                                                    <span class="inline-flex items-center rounded-full border border-violet-200 bg-violet-100 px-2 py-1 text-[0.7rem] font-extrabold text-violet-800" title="Privé">
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

        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-extrabold text-[color:var(--fam-text)]">Calendrier Famille pour Androïd</div>
                </div>
                <div class="shrink-0">
                    @if($googleSyncEnabled)
                        <span class="inline-flex items-center h-8 px-3 rounded-full bg-emerald-50 text-emerald-800 text-xs font-extrabold border border-emerald-200">Actif</span>
                    @elseif($googleConnected)
                        <span class="inline-flex items-center h-8 px-3 rounded-full bg-slate-50 text-slate-700 text-xs font-extrabold border border-slate-200">Connecté</span>
                    @else
                        <span class="inline-flex items-center h-8 px-3 rounded-full bg-slate-50 text-slate-700 text-xs font-extrabold border border-slate-200">Non connecté</span>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                <a href="{{ route('profile.edit') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">Configurer / Gérer</a>
            </div>
        </div>
    </div>
</x-app-layout>
