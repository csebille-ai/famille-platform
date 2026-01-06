<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Éditer la vidéo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('videos.update', $video) }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="title" class="block font-medium text-sm text-gray-700">
                                {{ __('Titre') }}
                            </label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                value="{{ old('title', $video->title) }}"
                                required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />
                            @error('title')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="category" class="block font-medium text-sm text-gray-700">
                                {{ __('Catégorie') }}
                            </label>
                            <select
                                id="category"
                                name="category"
                                required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            >
                                <option value="films" {{ old('category', $video->category) === 'films' ? 'selected' : '' }}>Films</option>
                                <option value="series" {{ old('category', $video->category) === 'series' ? 'selected' : '' }}>Séries</option>
                                <option value="docs" {{ old('category', $video->category) === 'docs' ? 'selected' : '' }}>Documentaires</option>
                            </select>
                            @error('category')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="video_file" class="block font-medium text-sm text-gray-700">
                                {{ __('Fichier vidéo') }}
                            </label>
                            <input
                                id="video_file"
                                type="file"
                                name="video_file"
                                accept="video/*"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />
                            @error('video_file')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 mt-1">Laissez vide pour conserver la vidéo actuelle. Formats acceptés : mp4, webm, avi, mov, mkv (max 3 GB)</p>
                        </div>

                        <div>
                            <label for="poster_file" class="block font-medium text-sm text-gray-700">
                                {{ __('Image (poster) (optionnel)') }}
                            </label>
                            <input
                                id="poster_file"
                                type="file"
                                name="poster_file"
                                accept="image/*"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />
                            @error('poster_file')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 mt-1">Laissez vide pour conserver le poster actuel (max 10 MB).</p>
                        </div>

                        <div>
                            <label for="description" class="block font-medium text-sm text-gray-700">
                                {{ __('Description') }}
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                rows="4"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            >{{ old('description', $video->description) }}</textarea>
                            @error('description')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('videos.show', $video) }}" class="text-gray-600 hover:text-gray-900">
                                {{ __('Annuler') }}
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                {{ __('Mettre à jour') }}
                            </button>
                        </div>
                    </form>
                </div>
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
