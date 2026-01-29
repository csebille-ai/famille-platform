<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">Playlists</div>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Modifier la playlist') }}
                </h2>
            </div>

            <a href="{{ route('playlists.show', $playlist) }}" class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">
                Retour
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($errors->any())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-red-600">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('playlists.update', $playlist) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <div class="text-sm font-semibold text-gray-900">Infos</div>

                            <div class="mt-4">
                                <x-input-label for="name" :value="__('Nom')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $playlist->name)" required autofocus />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div class="mt-5">
                                <input type="hidden" name="is_shared" value="0" />
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_shared" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_shared', $playlist->is_shared ? 1 : 0)) />
                                    <span>Partager (visible par tous les utilisateurs connectés)</span>
                                </label>
                                <x-input-error class="mt-2" :messages="$errors->get('is_shared')" />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <div class="text-sm font-semibold text-gray-900">Playlist Spotify (optionnel)</div>
                            <div class="mt-1 text-xs text-gray-500">Colle l'URL d'une playlist Spotify pour l'intégrer directement.</div>

                            <div class="mt-4">
                                <x-input-label for="spotify_playlist_url" :value="__('URL ou ID Spotify')" />
                                @php
                                    $spotifyUrl = $playlist->spotify_playlist_id
                                        ? 'https://open.spotify.com/playlist/' . $playlist->spotify_playlist_id
                                        : '';
                                @endphp
                                <x-text-input id="spotify_playlist_url" name="spotify_playlist_url" type="text" class="mt-1 block w-full" :value="old('spotify_playlist_url', $spotifyUrl)" placeholder="https://open.spotify.com/playlist/..." />
                                <x-input-error class="mt-2" :messages="$errors->get('spotify_playlist_url')" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('playlists.show', $playlist) }}" class="text-sm text-gray-700 hover:underline">Annuler</a>
                            <x-primary-button>
                                Enregistrer
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
