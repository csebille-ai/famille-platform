<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Vidéos') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $videosByCategory = $videos->getCollection()->groupBy('category');
                $categories = [
                    'films' => ['label' => 'Films', 'description' => 'Films et longs-métrages'],
                    'series' => ['label' => 'Séries', 'description' => 'Séries TV et épisodes'],
                    'docs' => ['label' => 'Documentaires', 'description' => 'Documentaires et contenus éducatifs'],
                ];
            @endphp

            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($categories as $key => $meta)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 {{ $category === $key ? 'border-2 border-indigo-500' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-lg font-semibold text-gray-900">{{ $meta['label'] }}</div>
                                <div class="mt-1 text-sm text-gray-600">{{ $meta['description'] }}</div>
                            </div>
                            <a href="{{ route('videos.index', ['category' => $key]) }}" class="text-sm text-indigo-600 hover:text-indigo-900 hover:underline whitespace-nowrap">
                                Voir
                            </a>
                        </div>

                        @php
                            $catVideos = $videosByCategory->get($key, collect());
                        @endphp

                        <div class="mt-4 space-y-2">
                            @forelse ($catVideos as $video)
                                <a href="{{ route('videos.show', $video) }}" class="flex items-center gap-3 group">
                                    <div class="w-20 aspect-video bg-gray-100 rounded overflow-hidden flex items-center justify-center shrink-0">
                                        @if ($video->poster_path)
                                            <img src="{{ route('videos.poster', $video) }}" alt="{{ $video->title }}" class="w-full h-full object-cover" loading="lazy" />
                                        @else
                                            <span class="text-gray-400 text-xs">No poster</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm text-gray-900 group-hover:underline truncate">▶ {{ $video->title }}</div>
                                        <div class="text-xs text-gray-500">{{ $video->created_at->diffForHumans() }}</div>
                                    </div>
                                </a>
                            @empty
                                <p class="text-sm text-gray-500">Aucune vidéo.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($videos->hasPages())
                <div class="mb-6">
                    {{ $videos->links() }}
                </div>
            @endif

            <!-- Formulaire d'upload -->
            <div id="upload" class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Ajouter une vidéo</h3>
                <form method="POST" action="{{ route('videos.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="title" class="block font-medium text-sm text-gray-700 mb-1">Titre *</label>
                            <input type="text" id="title" name="title" required value="{{ old('title') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            @error('title') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="category" class="block font-medium text-sm text-gray-700 mb-1">Catégorie *</label>
                            <select id="category" name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Choisir --</option>
                                <option value="films" {{ old('category') === 'films' ? 'selected' : '' }}>Films</option>
                                <option value="series" {{ old('category') === 'series' ? 'selected' : '' }}>Séries</option>
                                <option value="docs" {{ old('category') === 'docs' ? 'selected' : '' }}>Documentaires</option>
                            </select>
                            @error('category') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="video_file" class="block font-medium text-sm text-gray-700 mb-1">Fichier vidéo *</label>
                            <input type="file" id="video_file" name="video_file" accept="video/*" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            @error('video_file') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-500 mt-1">Max 3 GB</p>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block font-medium text-sm text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">{{ old('description') }}</textarea>
                        @error('description') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Envoyer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
