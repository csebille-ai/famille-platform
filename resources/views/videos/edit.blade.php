<x-app-layout>
    @php
        $maxVideoMb = max(1, (int) floor(((int) config('videos.max_upload_kb', 2097152)) / 1024));
        $maxVideoLabel = $maxVideoMb >= 1024
            ? (string) ((int) floor($maxVideoMb / 1024)) . ' GB'
            : (string) $maxVideoMb . ' MB';
    @endphp
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
                            <p class="microcopy text-xs text-gray-500 mt-1">Laissez vide pour conserver la vidéo actuelle. Formats acceptés : mp4, webm, avi, mov, mkv (max {{ $maxVideoLabel }})</p>
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
</x-app-layout>
