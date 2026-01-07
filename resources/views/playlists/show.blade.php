<x-app-layout>
    <x-slot name="header">
        @php
            $isOwner = (int) ($playlist->created_by ?? 0) === (int) auth()->id();
            $canAddItems = (bool) ($playlist->is_shared || $isOwner);
            $canRemoveItems = auth()->check() && Illuminate\Support\Facades\Gate::allows('playlists-delete-items');
        @endphp

        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">Playlists</div>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">
                    {{ $playlist->name }}
                </h2>
                <div class="mt-2 flex items-center gap-2">
                    @if ($playlist->is_shared)
                        <span class="text-xs px-2.5 py-1 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-800">Partagée</span>
                    @else
                        <span class="text-xs px-2.5 py-1 rounded-full border border-gray-200 bg-gray-50 text-gray-700">Privée</span>
                    @endif
                    <span class="text-xs text-gray-500">Par {{ $playlist->creator?->name ?? '—' }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('playlists.index') }}" class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">Retour</a>

                @if ($isOwner)
                    <a href="{{ route('playlists.edit', $playlist) }}" class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">Modifier</a>

                    <form method="POST" action="{{ route('playlists.destroy', $playlist) }}" onsubmit="return confirm('Supprimer cette playlist ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-gray-900 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-gray-700">
                            Supprimer
                        </button>
                    </form>
                @endif
            </div>
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

            @if ($canAddItems)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="text-xs text-gray-500">Ajouter un morceau</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">Lien Spotify</div>
                        <div class="mt-1 text-sm text-gray-600">Exemples: <span class="font-mono">https://open.spotify.com/track/…</span> ou <span class="font-mono">spotify:track:…</span></div>

                        <form method="POST" action="{{ route('playlists.items.store', $playlist) }}" class="mt-5 grid gap-4 sm:grid-cols-3">
                            @csrf

                            <div class="sm:col-span-2">
                                <x-input-label for="spotify" :value="__('Spotify (track)')" />
                                <x-text-input id="spotify" name="spotify" type="text" class="mt-1 block w-full" :value="old('spotify')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('spotify')" />
                            </div>

                            <div>
                                <x-input-label for="label" :value="__('Label (optionnel)')" />
                                <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label')" />
                                <x-input-error class="mt-2" :messages="$errors->get('label')" />
                            </div>

                            <div class="sm:col-span-3 flex justify-end">
                                <x-primary-button>
                                    Ajouter
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="text-xs text-gray-500">Morceaux</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $playlist->items->count() }} morceau(x)</div>

                    <div class="mt-6 space-y-4">
                        @forelse ($playlist->items as $item)
                            <div class="rounded-2xl border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <a
                                            href="https://open.spotify.com/track/{{ $item->spotify_track_id }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-sm font-semibold text-gray-900 hover:underline"
                                        >
                                            {{ $item->label ?: 'Ouvrir dans Spotify' }}
                                        </a>
                                        <div class="mt-1 text-xs text-gray-500">Ajouté par {{ $item->adder?->name ?? '—' }}</div>
                                    </div>

                                    @if ($canRemoveItems)
                                        <div class="flex items-center gap-2">
                                            <a
                                                href="https://open.spotify.com/track/{{ $item->spotify_track_id }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                            >
                                                Ouvrir
                                            </a>

                                            <form method="POST" action="{{ route('playlists.items.destroy', [$playlist, $item]) }}" onsubmit="return confirm('Retirer ce morceau ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">Retirer</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    <iframe
                                        style="border-radius:12px"
                                        src="https://open.spotify.com/embed/track/{{ $item->spotify_track_id }}"
                                        width="100%"
                                        height="152"
                                        frameborder="0"
                                        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                                        loading="lazy"
                                    ></iframe>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-gray-200 p-6 text-sm text-gray-600">Aucun morceau pour l’instant.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
