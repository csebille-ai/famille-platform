<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-700">Playlists</h3>
                        <div class="mt-3 flex items-center justify-between gap-4 rounded-2xl border border-gray-200 p-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Playlists Spotify</div>
                                <div class="mt-1 text-sm text-gray-600">Crée et partage des playlists en collant des liens Spotify.</div>
                            </div>

                            <a href="{{ route('playlists.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Ouvrir
                            </a>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-700">Dernières images uploadées</h3>

                        @if(($latestImages ?? collect())->count())
                            <div class="mt-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                @foreach($latestImages as $img)
                                    <a href="{{ route('images.view', $img) }}" class="block">
                                        <img
                                          src="{{ route('images.view', $img) }}"
                                          alt="{{ $img->name ?? 'image' }}"
                                          class="w-full h-24 object-cover rounded"
                                          loading="lazy"
                                        />
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-3 text-sm text-gray-500">
                                Aucune image uploadée pour l’instant.
                            </div>
                        @endif
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-700">Dernières vidéos uploadées</h3>

                        @if(($latestVideos ?? collect())->count())
                            <div class="mt-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                @foreach($latestVideos as $video)
                                    <a href="{{ route('videos.show', $video) }}" class="block">
                                        <img
                                          src="{{ route('videos.poster', $video) }}"
                                          alt="{{ $video->name ?? 'video' }}"
                                          class="w-full h-24 object-cover rounded"
                                          loading="lazy"
                                        />
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-3 text-sm text-gray-500">
                                Aucune vidéo uploadée pour l’instant.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
