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

                        <div>
                            <label for="poster_file" class="block font-medium text-sm text-gray-700 mb-1">Image (poster) (optionnel)</label>
                            <input type="file" id="poster_file" name="poster_file" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            @error('poster_file') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-500 mt-1">PNG/JPG, conseillé: petite image (≤ 10 MB)</p>
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

    <script>
        (function () {
            const videoInput = document.getElementById('video_file');
            const posterInput = document.getElementById('poster_file');
            if (!videoInput || !posterInput) return;

            async function generatePosterFile(file) {
                const url = URL.createObjectURL(file);
                const video = document.createElement('video');
                video.preload = 'metadata';
                video.muted = true;
                video.playsInline = true;

                try {
                    await new Promise((resolve, reject) => {
                        video.onloadedmetadata = () => resolve();
                        video.onerror = () => reject(new Error('video load failed'));
                        video.src = url;
                    });

                    const duration = Number.isFinite(video.duration) ? video.duration : 0;
                    const targetTime = Math.min(Math.max(1, duration > 0 ? 1 : 0), Math.max(0, duration - 0.1));
                    if (targetTime > 0) {
                        await new Promise((resolve) => {
                            video.onseeked = () => resolve();
                            try { video.currentTime = targetTime; } catch { resolve(); }
                        });
                    }

                    const width = video.videoWidth || 0;
                    const height = video.videoHeight || 0;
                    if (!width || !height) return null;

                    const maxWidth = 640;
                    const scale = Math.min(1, maxWidth / width);
                    const outW = Math.max(1, Math.floor(width * scale));
                    const outH = Math.max(1, Math.floor(height * scale));

                    const canvas = document.createElement('canvas');
                    canvas.width = outW;
                    canvas.height = outH;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return null;
                    ctx.drawImage(video, 0, 0, outW, outH);

                    const blob = await new Promise((resolve) => {
                        canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
                    });
                    if (!blob) return null;

                    const safeBase = (file.name || 'video').replace(/\.[^.]+$/, '').replace(/[^a-z0-9_-]+/gi, '-').slice(0, 50);
                    const posterName = (safeBase || 'poster') + '.jpg';
                    return new File([blob], posterName, { type: 'image/jpeg' });
                } finally {
                    URL.revokeObjectURL(url);
                }
            }

            async function maybeAutoSetPoster() {
                const file = videoInput.files && videoInput.files[0];
                if (!file) return;
                if (posterInput.files && posterInput.files.length > 0) return;

                try {
                    const posterFile = await generatePosterFile(file);
                    if (!posterFile) return;
                    const dt = new DataTransfer();
                    dt.items.add(posterFile);
                    posterInput.files = dt.files;
                } catch {
                    // Ignore; manual poster remains available.
                }
            }

            videoInput.addEventListener('change', () => {
                void maybeAutoSetPoster();
            });
        })();
    </script>
</x-app-layout>
