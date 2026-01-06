<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Playlists') }}
            </h2>

            <a href="{{ route('playlists.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Create') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="text-xs text-gray-500">Spotify</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">Mes playlists (et celles partagées)</div>
                    <div class="mt-1 text-sm text-gray-600">Ajoute des morceaux via un lien Spotify (track) ou un URI <span class="font-mono">spotify:track:…</span>.</div>

                    <div class="mt-6 space-y-3">
                        @forelse ($playlists as $playlist)
                            <a href="{{ route('playlists.show', $playlist) }}" class="block rounded-2xl border border-gray-200 p-4 hover:bg-gray-50">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm font-semibold text-gray-900">{{ $playlist->name }}</div>
                                            @if ($playlist->is_shared)
                                                <span class="text-xs px-2.5 py-1 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-800">Partagée</span>
                                            @else
                                                <span class="text-xs px-2.5 py-1 rounded-full border border-gray-200 bg-gray-50 text-gray-700">Privée</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">Par {{ $playlist->creator?->name ?? '—' }}</div>
                                    </div>

                                    <div class="text-xs text-gray-500">{{ $playlist->items_count ?? 0 }} morceau(x)</div>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-2xl border border-gray-200 p-6 text-sm text-gray-600">
                                Aucune playlist pour l’instant.
                                <a href="{{ route('playlists.create') }}" class="text-gray-900 font-semibold hover:underline">Créer la première</a>.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
