<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Google Agenda</h2>
        <p class="mt-1 text-sm text-gray-600">Connecte ton compte Google Agenda pour ajouter des événements directement (sans écran Google).</p>
    </header>

    @php
        $googleConnected = (bool) ($googleConnected ?? false);
    @endphp

    <div class="mt-6">
        @if($googleConnected)
            <div class="flex items-center justify-between gap-4">
                <div class="text-sm font-semibold text-emerald-700">Connecté</div>
                <form method="POST" action="{{ route('oauth.google.calendar.disconnect') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-rose-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-rose-500 focus:bg-rose-500 active:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition ease-in-out duration-150">Déconnecter</button>
                </form>
            </div>
        @else
            <div class="flex items-center justify-between gap-4">
                <div class="text-sm font-semibold text-gray-700">Non connecté</div>
                <a href="{{ route('oauth.google.calendar.start') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Connecter Google Agenda</a>
            </div>
        @endif

        @if (session('status') === 'google-calendar-connected')
            <p class="mt-2 text-sm text-gray-600">Google Agenda connecté.</p>
        @elseif (session('status') === 'google-calendar-disconnected')
            <p class="mt-2 text-sm text-gray-600">Google Agenda déconnecté.</p>
        @elseif (session('status') === 'google-calendar-error')
            <p class="mt-2 text-sm text-rose-600">Connexion Google Agenda échouée. Réessaie.</p>
        @endif
    </div>
</section>
