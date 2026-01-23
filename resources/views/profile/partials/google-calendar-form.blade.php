<section>
    <header>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-[color:var(--fam-text)]">Intégration Google Agenda</h2>
                <p class="microcopy mt-1 text-xs text-[color:var(--fam-muted)]">Synchronise automatiquement les événements dans un calendrier dédié.</p>
            </div>

            @php
                $googleConnected = (bool) ($googleConnected ?? false);
                $googleSyncEnabled = (bool) ($googleSyncEnabled ?? false);

                [$badgeLabel, $badgeClass] = $googleConnected
                    ? ['Connecté', 'border-emerald-200 bg-emerald-50 text-emerald-800']
                    : ['Non connecté', 'border-[color:var(--fam-border)] bg-white text-[color:var(--fam-muted)]'];
            @endphp

            <span class="shrink-0 inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass }}">
                {{ $badgeLabel }}
            </span>
        </div>
    </header>

    <div class="mt-5">
        @if($googleConnected)
            <div class="rounded-2xl border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-2)] p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Calendrier: <span class="text-[color:var(--fam-muted)]">Famille — Calendrier</span></div>

                    <div class="flex items-center gap-2">
                        <a href="https://calendar.google.com/" target="_blank" rel="noopener" class="inline-flex items-center justify-center h-9 px-3 rounded-xl bg-white border border-[color:var(--fam-border)] text-[color:var(--fam-text)] text-xs font-semibold hover:bg-[color:var(--fam-tint)]">
                            Gérer
                        </a>

                        <form method="POST" action="{{ route('oauth.google.calendar.disconnect') }}" onsubmit="return confirm('Déconnecter Google Agenda ?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center h-9 px-3 rounded-xl bg-white border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">
                                Déconnecter
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Synchronisation</div>
                        <div class="microcopy mt-0.5 text-xs text-[color:var(--fam-muted)]">Active/désactive la synchro automatique.</div>
                    </div>

                    <form method="POST" action="{{ route('oauth.google.calendar.toggle') }}">
                        @csrf
                        <button type="submit" role="switch" aria-checked="{{ $googleSyncEnabled ? 'true' : 'false' }}" class="relative inline-flex h-9 w-16 items-center rounded-full border transition {{ $googleSyncEnabled ? 'bg-[color:var(--fam-primary-200)] border-[color:var(--fam-primary-300)]' : 'bg-white border-[color:var(--fam-border)]' }}">
                            <span class="absolute left-2 text-[10px] font-extrabold {{ $googleSyncEnabled ? 'text-[color:var(--fam-primary-hover)]' : 'text-[color:var(--fam-muted)]' }}">
                                {{ $googleSyncEnabled ? 'ON' : 'OFF' }}
                            </span>
                            <span class="inline-block h-7 w-7 rounded-full bg-white border border-[color:var(--fam-border)] shadow-sm transform transition {{ $googleSyncEnabled ? 'translate-x-8' : 'translate-x-1' }}"></span>
                        </button>
                    </form>
                </div>

                <div class="mt-3 flex items-center justify-end">
                    <form method="POST" action="{{ route('oauth.google.calendar.resync') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center h-9 px-3 rounded-xl bg-white border border-[color:var(--fam-border)] text-[color:var(--fam-text)] text-xs font-semibold hover:bg-[color:var(--fam-tint)]">
                            Resynchroniser maintenant
                        </button>
                    </form>
                </div>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Connexion</div>
                <a href="{{ route('oauth.google.calendar.start') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-2xl bg-[color:var(--fam-primary)] text-white text-sm font-extrabold hover:bg-[color:var(--fam-primary-hover)]">
                    Connecter Google Agenda
                </a>
            </div>

            <p class="mt-3 text-xs text-[color:var(--fam-muted)]">
                Si Google affiche <span class="font-semibold text-[color:var(--fam-text)]">Erreur 403: access_denied</span> (application en test), ajoute ton compte dans
                <span class="font-semibold text-[color:var(--fam-text)]">Google Cloud Console → OAuth consent screen → Test users</span>.
            </p>
        @endif

        @if (session('status') === 'google-calendar-connected')
            <p class="mt-3 text-xs font-semibold text-emerald-700">Google Agenda connecté.</p>
        @elseif (session('status') === 'google-calendar-disconnected')
            <p class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Google Agenda déconnecté.</p>
        @elseif (session('status') === 'google-calendar-sync-enabled')
            <p class="mt-3 text-xs font-semibold text-emerald-700">Synchronisation activée.</p>
        @elseif (session('status') === 'google-calendar-sync-disabled')
            <p class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">Synchronisation désactivée.</p>
        @elseif (session('status') === 'google-calendar-resync-started')
            <p class="mt-3 text-xs font-semibold text-emerald-700">Resynchronisation lancée.</p>
        @elseif (session('status') === 'google-calendar-error')
            <p class="mt-3 text-xs font-semibold text-rose-700">Connexion Google Agenda échouée. Réessaie.</p>
        @elseif (session('status') === 'google-calendar-misconfigured')
            <p class="mt-3 text-xs font-semibold text-rose-700">Connexion Google Agenda impossible: configuration manquante (GOOGLE_CLIENT_ID / GOOGLE_REDIRECT_URI / APP_URL).</p>
        @endif
    </div>
</section>
