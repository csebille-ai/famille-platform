<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Google Agenda</h2>
        <p class="mt-1 text-sm text-gray-600">Connecte ton compte Google Agenda pour synchroniser automatiquement les événements dans un calendrier dédié.</p>
    </header>

    @php
        $googleConnected = (bool) ($googleConnected ?? false);
        $googleSyncEnabled = (bool) ($googleSyncEnabled ?? false);
    @endphp

    <div class="mt-6">
        @if($googleConnected)
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <div class="text-sm font-semibold text-emerald-700">Connecté</div>
                    <form method="POST" action="{{ route('oauth.google.calendar.disconnect') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-rose-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-rose-500 focus:bg-rose-500 active:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition ease-in-out duration-150">Déconnecter</button>
                    </form>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('oauth.google.calendar.toggle') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 {{ $googleSyncEnabled ? 'bg-emerald-600 hover:bg-emerald-500 focus:bg-emerald-500 active:bg-emerald-700 focus:ring-emerald-500' : 'bg-gray-800 hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:ring-indigo-500' }} border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ $googleSyncEnabled ? 'Synchro activée (désactiver)' : 'Synchro désactivée (activer)' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('oauth.google.calendar.resync') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:bg-gray-50 active:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Resynchroniser maintenant</button>
                    </form>
                </div>

                <p class="text-sm text-gray-600">Le calendrier dédié s'appelle <span class="font-semibold">Famille — Calendrier</span>.</p>
            </div>
        @else
            <div class="flex items-center justify-between gap-4">
                <div class="text-sm font-semibold text-gray-700">Non connecté</div>
                <a href="{{ route('oauth.google.calendar.start') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Connecter Google Agenda</a>
            </div>

            <p class="mt-2 text-xs text-gray-600">
                Si Google affiche <span class="font-semibold">Erreur 403: access_denied</span> (application en test / non validée), ajoute ton compte dans
                <span class="font-semibold">Google Cloud Console → OAuth consent screen → Test users</span>, ou passe l’application en production.
            </p>
        @endif

        @if (session('status') === 'google-calendar-connected')
            <p class="mt-2 text-sm text-gray-600">Google Agenda connecté. Synchronisation en cours.</p>
        @elseif (session('status') === 'google-calendar-disconnected')
            <p class="mt-2 text-sm text-gray-600">Google Agenda déconnecté.</p>
        @elseif (session('status') === 'google-calendar-sync-enabled')
            <p class="mt-2 text-sm text-gray-600">Synchronisation activée.</p>
        @elseif (session('status') === 'google-calendar-sync-disabled')
            <p class="mt-2 text-sm text-gray-600">Synchronisation désactivée.</p>
        @elseif (session('status') === 'google-calendar-resync-started')
            <p class="mt-2 text-sm text-gray-600">Resynchronisation lancée.</p>
        @elseif (session('status') === 'google-calendar-error')
            <p class="mt-2 text-sm text-rose-600">Connexion Google Agenda échouée. Réessaie.</p>
        @elseif (session('status') === 'google-calendar-misconfigured')
            <p class="mt-2 text-sm text-rose-600">Connexion Google Agenda impossible: configuration manquante (GOOGLE_CLIENT_ID / GOOGLE_REDIRECT_URI / APP_URL). Vérifie aussi l’URL de redirection autorisée dans Google Cloud Console.</p>
        @endif
    </div>
</section>
