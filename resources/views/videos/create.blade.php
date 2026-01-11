<x-app-layout>
    @php
        $maxVideoMb = max(1, (int) floor(((int) config('videos.max_upload_kb', 2097152)) / 1024));
        $maxVideoLabel = $maxVideoMb >= 1024
            ? (string) ((int) floor($maxVideoMb / 1024)) . ' GB'
            : (string) $maxVideoMb . ' MB';

        $mode = (string) request()->query('mode', '');
        $isPersonal = $mode === 'personal';
        $returnPath = (string) request()->query('return', '');

        $prefCategory = strtolower((string) request()->query('category', ''));
        if (!in_array($prefCategory, ['films', 'series', 'docs'], true)) {
            $prefCategory = '';
        }
    @endphp
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $isPersonal ? __('Importer une vidéo perso') : __('Importer une vidéo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('videos.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        @if($returnPath !== '')
                            <input type="hidden" name="return" value="{{ $returnPath }}" />
                        @endif

                        <div>
                            <label for="title" class="block font-medium text-sm text-gray-700">
                                {{ __('Titre') }}
                            </label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                value="{{ old('title') }}"
                                required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />
                            @error('title')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        @if($isPersonal)
                            <input type="hidden" name="category" value="docs" />
                        @else
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
                                    <option value="">-- Choisir --</option>
                                    <option value="films" {{ (old('category') === 'films' || (old('category') === null && $prefCategory === 'films')) ? 'selected' : '' }}>Films</option>
                                    <option value="series" {{ (old('category') === 'series' || (old('category') === null && $prefCategory === 'series')) ? 'selected' : '' }}>Séries</option>
                                    <option value="docs" {{ (old('category') === 'docs' || (old('category') === null && $prefCategory === 'docs')) ? 'selected' : '' }}>Documentaires</option>
                                </select>
                                @error('category')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <label for="video_file" class="block font-medium text-sm text-gray-700">
                                {{ __('Fichier vidéo') }}
                            </label>
                            <input
                                id="video_file"
                                type="file"
                                name="video_file"
                                accept="video/*"
                                required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />

                            <input id="poster_file" type="file" name="poster_file" accept="image/*" class="hidden" />

                            @error('video_file')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 mt-1">Formats acceptés : mp4, webm, avi, mov, mkv (max {{ $maxVideoLabel }})</p>

                            <div id="poster_status" class="text-xs text-slate-500 mt-2">Miniature : génération automatique…</div>
                            <img id="poster_preview" alt="" class="mt-2 hidden w-40 aspect-video rounded-lg object-cover" />
                        </div>

                        <div>
                            <label for="description" class="block font-medium text-sm text-gray-700">
                                {{ __('Description (optionnel)') }}
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                rows="4"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            >{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ $returnPath !== '' ? $returnPath : route('videos.index') }}" class="text-gray-600 hover:text-gray-900">
                                {{ __('Annuler') }}
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                {{ __('Importer la vidéo') }}
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
            const posterStatus = document.getElementById('poster_status');
            const posterPreview = document.getElementById('poster_preview');

            if (!videoInput || !posterInput) return;

            let previewUrl = null;

            const setStatus = (txt) => {
                if (posterStatus) posterStatus.textContent = txt;
            };

            const clearPreview = () => {
                if (previewUrl) {
                    try { URL.revokeObjectURL(previewUrl); } catch (e) {}
                    previewUrl = null;
                }
                if (posterPreview) {
                    posterPreview.src = '';
                    posterPreview.classList.add('hidden');
                }
            };

            const wait = (target, eventName, timeoutMs) => {
                return new Promise((resolve, reject) => {
                    let done = false;
                    const onDone = () => {
                        if (done) return;
                        done = true;
                        cleanup();
                        resolve();
                    };
                    const onErr = (e) => {
                        if (done) return;
                        done = true;
                        cleanup();
                        reject(e);
                    };
                    const cleanup = () => {
                        clearTimeout(timer);
                        target.removeEventListener(eventName, onDone);
                        target.removeEventListener('error', onErr);
                    };
                    const timer = setTimeout(() => onErr(new Error('timeout:' + eventName)), timeoutMs);
                    target.addEventListener(eventName, onDone, { once: true });
                    target.addEventListener('error', onErr, { once: true });
                });
            };

            const buildPosterBlob = async (file) => {
                if (!file) return null;
                if (!('URL' in window) || !('createObjectURL' in URL)) return null;

                const url = URL.createObjectURL(file);
                try {
                    const video = document.createElement('video');
                    video.preload = 'metadata';
                    video.muted = true;
                    video.playsInline = true;
                    video.src = url;

                    await wait(video, 'loadedmetadata', 8000);
                    const duration = Number(video.duration || 0);
                    const target = (Number.isFinite(duration) && duration > 2) ? 1 : 0;
                    try { video.currentTime = target; } catch (e) { /* ignore */ }
                    await wait(video, 'seeked', 8000);

                    const w = video.videoWidth || 0;
                    const h = video.videoHeight || 0;
                    if (!w || !h) return null;

                    const maxW = 640;
                    const scale = Math.min(1, maxW / w);
                    const cw = Math.max(1, Math.round(w * scale));
                    const ch = Math.max(1, Math.round(h * scale));

                    const canvas = document.createElement('canvas');
                    canvas.width = cw;
                    canvas.height = ch;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return null;
                    ctx.drawImage(video, 0, 0, cw, ch);

                    const blob = await new Promise((resolve) => {
                        canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
                    });
                    return blob || null;
                } catch (e) {
                    return null;
                } finally {
                    try { URL.revokeObjectURL(url); } catch (e) {}
                }
            };

            const setPosterFile = (blob) => {
                try {
                    if (!blob || !('DataTransfer' in window)) return false;
                    const dt = new DataTransfer();
                    dt.items.add(new File([blob], 'poster.jpg', { type: blob.type || 'image/jpeg' }));
                    posterInput.files = dt.files;
                    return posterInput.files && posterInput.files.length > 0;
                } catch (e) {
                    return false;
                }
            };

            videoInput.addEventListener('change', async () => {
                clearPreview();

                const file = videoInput.files && videoInput.files[0];
                if (!file) {
                    setStatus('Miniature : aucune');
                    return;
                }

                setStatus('Miniature : génération automatique…');
                const blob = await buildPosterBlob(file);
                if (!blob) {
                    setStatus('Miniature : non générée (le navigateur ne supporte pas la capture)');
                    return;
                }

                const ok = setPosterFile(blob);
                if (!ok) {
                    setStatus('Miniature : non attachée (support limité)');
                    return;
                }

                setStatus('Miniature : générée automatiquement');
                if (posterPreview) {
                    previewUrl = URL.createObjectURL(blob);
                    posterPreview.src = previewUrl;
                    posterPreview.classList.remove('hidden');
                }
            });

            setStatus('Miniature : génération automatique…');
        })();
    </script>
</x-app-layout>
