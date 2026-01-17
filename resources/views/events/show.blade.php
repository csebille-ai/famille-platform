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
                @can('update', $event)
                    <a href="{{ route('events.edit', $event) }}" class="inline-flex items-center h-10 px-3 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">Modifier</a>
                @endcan
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Date</div>
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">
                        @if($event->all_day)
                            @if($startAt)
                                {{ $startAt->timezone($tz)->translatedFormat('D j M Y') }}
                            @else
                                —
                            @endif
                            <span class="text-[color:var(--fam-muted)]">(journée entière)</span>
                        @else
                            @if($startAt)
                                {{ $startAt->timezone($tz)->translatedFormat('D j M Y \à H:i') }}
                            @else
                                —
                            @endif
                            @if($endAt)
                                <span class="text-[color:var(--fam-muted)]">→</span>
                                {{ $endAt->timezone($tz)->translatedFormat('D j M Y \à H:i') }}
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
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $event->location }}</div>
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
                    <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]">{{ $event->status ?: 'active' }}</div>
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
                                {{ $event->reminder_at->timezone($tz)->translatedFormat('D j M Y \à H:i') }}
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
