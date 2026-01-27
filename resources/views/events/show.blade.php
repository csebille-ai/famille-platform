<x-app-layout pageBgClass="fam-page-bg">
    @php
        /** @var \App\Models\Event $event */
        $isPrivate = ($event->visibility ?? 'family') === 'private';
        $isImportant = (bool) ($event->is_important ?? false);

        $categoryLabel = match ($event->category) {
            'anniversary' => 'Anniversaire',
            'school' => 'École',
            'medical' => 'Médical',
            'travel' => 'Voyage',
            'family' => 'Famille',
            'other' => 'Autre',
            default => 'Autre',
        };

        $startAt = $event->start_at;
        $endAt = $event->end_at;
        $tz = $event->timezone ?: config('app.timezone');

        $statusLabel = match ($event->status ?: 'active') {
            'active' => 'Actif',
            'cancelled' => 'Annulé',
            'archived' => 'Archivé',
            default => 'Actif',
        };

		$hasFamilyCalendar = (bool) ($hasFamilyCalendar ?? false);
        $googleConnected = (bool) ($googleConnected ?? false);
        $googleSyncEnabled = (bool) ($googleSyncEnabled ?? false);
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-4 space-y-3">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="text-base font-semibold text-[color:var(--fam-text)] break-words">{{ $event->title }}</div>
                    @if($isImportant)
                        <span class="inline-flex items-center h-6 px-2 rounded-full bg-amber-50 text-amber-900 text-xs font-extrabold border border-amber-200">Important</span>
                    @endif
                    @if($isPrivate)
                        <span class="inline-flex items-center h-6 px-2 rounded-full bg-slate-50 text-slate-800 text-xs font-extrabold border border-slate-200">Privé</span>
                    @endif
                </div>
                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">{{ $categoryLabel }}</div>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                <a href="{{ route('events.index') }}" class="inline-flex items-center h-10 px-3 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-semibold text-[color:var(--fam-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Liste</a>
                @if(!$hasFamilyCalendar && !$googleSyncEnabled)
                    <button
                        type="button"
                        id="eventAddToCalendarBtn"
                        class="inline-flex items-center h-10 px-3 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white text-sm font-extrabold text-[color:var(--fam-text)] hover:bg-[color:rgba(14,165,160,0.10)]"
                    >
                        Ajouter à mon agenda
                    </button>
                @else
                    <div class="hidden sm:block text-xs font-semibold text-[color:var(--fam-muted)]">
                        @if($hasFamilyCalendar)
                            Déjà inclus via Calendrier Famille
                        @elseif($googleSyncEnabled)
                            Synchronisé via Google
                        @endif
                    </div>
                @endif
                @can('update', $event)
                    <a href="{{ route('events.edit', $event) }}" class="inline-flex items-center h-10 px-3 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">Modifier</a>
                @endcan
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Google Agenda</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">
                        Synchronisation automatique dans ton calendrier dédié.
                    </div>
                </div>
                <div class="shrink-0">
                    @if(!$googleConnected)
                        <span class="inline-flex items-center h-7 px-2.5 rounded-full bg-slate-50 text-slate-700 text-xs font-extrabold border border-slate-200">Non connecté</span>
                    @elseif($googleSyncEnabled)
                        <span class="inline-flex items-center h-7 px-2.5 rounded-full bg-emerald-50 text-emerald-800 text-xs font-extrabold border border-emerald-200">Synchro activée</span>
                    @else
                        <span class="inline-flex items-center h-7 px-2.5 rounded-full bg-amber-50 text-amber-900 text-xs font-extrabold border border-amber-200">Synchro désactivée</span>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                @if(!$googleConnected)
                    <a href="{{ route('oauth.google.calendar.start') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">Connecter Google Agenda</a>
                @else
                    <a href="{{ route('profile.edit') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-white border border-[color:var(--fam-border)] text-[color:var(--fam-text)] text-sm font-extrabold hover:bg-[color:var(--fam-tint)]">Gérer la synchro</a>
                @endif
            </div>
        </div>

        @if(!$hasFamilyCalendar && !$googleSyncEnabled)
            <div id="eventAddToCalendarSheet" class="fixed inset-0 z-50 hidden" aria-hidden="true">
                <button type="button" id="eventAddToCalendarBackdrop" class="absolute inset-0 bg-black/35"></button>
                <div class="absolute inset-x-0 bottom-0 flex justify-center">
                    <div class="w-full max-w-[560px] rounded-t-3xl bg-white border border-[color:var(--fam-border-soft)] shadow-[0_-18px_55px_rgba(15,23,42,0.18)] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)]">
                        <div class="mx-auto h-1 w-9 rounded-full bg-black/10"></div>
                        <div class="mt-3 text-sm font-extrabold text-[color:var(--fam-text)]">Ajouter à mon agenda</div>
                        <div class="mt-3 grid gap-2">
                            <a
                                href="{{ $event->outlookCalendarUrl() }}"
                                target="_blank"
                                rel="noopener"
                                class="w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--fam-border)] bg-white px-4 text-sm font-semibold text-[color:var(--fam-text)] hover:bg-[color:var(--fam-tint)]"
                            >
                                <span>Outlook</span>
                                <i class="ph ph-microsoft-outlook-logo" aria-hidden="true"></i>
                            </a>
                        </div>
                        <button type="button" id="eventAddToCalendarClose" class="mt-3 w-full h-11 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-white/60 active:bg-white/75">Annuler</button>
                    </div>
                </div>
            </div>

            <script>
                (() => {
                    const btn = document.getElementById('eventAddToCalendarBtn');
                    const sheet = document.getElementById('eventAddToCalendarSheet');
                    const close = document.getElementById('eventAddToCalendarClose');
                    const backdrop = document.getElementById('eventAddToCalendarBackdrop');
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
                })();
            </script>
        @endif

        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Date</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">
                        @if($event->all_day)
                            @if($startAt)
                                {{ $startAt->timezone($tz)->locale(app()->getLocale())->translatedFormat('D j M Y') }}
                            @else
                                —
                            @endif
                            <span class="text-[color:var(--fam-muted)]">(journée entière)</span>
                        @else
                            @if($startAt)
                                {{ $startAt->timezone($tz)->locale(app()->getLocale())->translatedFormat('D j M Y \à H:i') }}
                            @else
                                —
                            @endif
                            @if($endAt)
                                <span class="text-[color:var(--fam-muted)]">→</span>
                                {{ $endAt->timezone($tz)->locale(app()->getLocale())->translatedFormat('D j M Y \à H:i') }}
                            @endif
                        @endif
                    </div>
                </div>
                @if($event->color_tag)
                    <div class="shrink-0 w-10 h-10 rounded-2xl border border-[color:var(--fam-border-soft)]" style="background: {{ $event->color_tag }};"></div>
                @endif
            </div>

            @if($event->location)
                <div>
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Lieu</div>
                    <div class="mt-1 flex items-start gap-2">
                        <div class="flex-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $event->location_label ?? $event->location }}</div>
                        @php
                            $hasCoords = $event->location_lat && $event->location_lon;
                            $mapsUrl = $hasCoords 
                                ? 'https://www.google.com/maps/dir/?api=1&destination=' . $event->location_lat . ',' . $event->location_lon
                                : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($event->location);
                        @endphp
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 h-9 px-3 rounded-2xl bg-[color:var(--fam-primary)] text-white text-xs font-extrabold hover:bg-[color:var(--fam-primary-hover)] active:scale-[0.98] transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M200,224H150.54A266.56,266.56,0,0,0,174,200.25c27.45-31.57,42-64.85,42-96.25a88,88,0,0,0-176,0c0,31.4,14.51,64.68,42,96.25A266.56,266.56,0,0,0,105.46,224H56a8,8,0,0,0,0,16H200a8,8,0,0,0,0-16ZM56,104a72,72,0,0,1,144,0c0,57.23-55.47,105-72,118C111.47,209,56,161.23,56,104Zm112,0a40,40,0,1,0-40,40A40,40,0,0,0,168,104Zm-64,0a24,24,0,1,1,24,24A24,24,0,0,1,104,104Z"></path></svg>
                            Itinéraire
                        </a>
                    </div>
                </div>
            @endif

            @if($event->description)
                <div>
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Description</div>
                    <div class="mt-1 text-sm text-[color:var(--fam-text)] whitespace-pre-line">{{ $event->description }}</div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Créé par</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">
                        {{ optional($event->createdBy)->name ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Statut</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $statusLabel }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Rappel</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">
                        @if(!$event->notify)
                            Désactivé
                        @else
                            @if($event->reminder_at)
                                {{ $event->reminder_at->timezone($tz)->locale(app()->getLocale())->translatedFormat('D j M Y \à H:i') }}
                            @else
                                Activé
                            @endif
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Visibilité</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $isPrivate ? 'Privé' : 'Famille' }}</div>
                </div>
            </div>
        </div>

        @can('delete', $event)
            <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Supprimer cet événement ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full inline-flex items-center justify-center h-11 px-4 rounded-2xl border border-rose-200 bg-rose-50 text-sm font-extrabold text-rose-800 hover:bg-rose-100">Supprimer</button>
            </form>
        @endcan
    </div>
</x-app-layout>
