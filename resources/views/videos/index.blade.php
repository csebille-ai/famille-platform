@php
    $selectedCategory = (string) ($category ?? '');
    $categories = [
        'films' => ['label' => 'Films', 'description' => 'Films et longs-métrages'],
        'series' => ['label' => 'Séries', 'description' => 'Séries TV et épisodes'],
        'docs' => ['label' => 'Documentaires', 'description' => 'Documentaires et contenus éducatifs'],
    ];

    $formatBytes = function (?int $bytes): string {
        $bytes = (int) ($bytes ?? 0);
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return rtrim(rtrim(number_format($value, $i === 0 ? 0 : 1, '.', ''), '0'), '.') . ' ' . $units[$i];
    };
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-6xl mx-auto px-6 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Vidéos</h1>
        </div>

        @if (session('status'))
            <div class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="text-sm font-semibold text-red-600">Erreur</div>
                <ul class="mt-2 space-y-1 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($categories as $key => $meta)
                @php($previews = ($categoryPreviews ?? [])[$key] ?? collect())

                <a href="{{ route('videos.index', ['category' => $key]) }}" class="bg-white rounded-2xl shadow-sm p-4 block">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold text-gray-900">{{ $meta['label'] }}</div>
                            <div class="mt-1 text-sm text-slate-500 truncate">{{ $meta['description'] }}</div>
                        </div>
                        <span class="text-sm font-semibold text-slate-900 whitespace-nowrap">Voir ›</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($previews as $video)
                            <div class="flex items-center gap-3">
                                <div class="w-14 h-14 rounded-xl bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center">
                                    @if (!empty($video->poster_path))
                                        <img src="{{ route('videos.poster', $video) }}" alt="{{ $video->title }}" class="w-full h-full object-cover" loading="lazy" />
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-slate-400" aria-hidden="true">
                                            <rect x="3" y="5" width="18" height="14" rx="2" />
                                            <path d="M10 9l5 3-5 3V9z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $video->title }}</div>
                                    <div class="text-xs text-slate-500">{{ $video->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Aucune vidéo.</div>
                        @endforelse
                    </div>
                </a>
            @endforeach
        </div>

        @if (!empty($selectedCategory) && $videos)
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold text-gray-900">{{ $categories[$selectedCategory]['label'] ?? 'Vidéos' }}</div>
                        <div class="text-sm text-slate-500 mt-1">Voir catégorie</div>
                    </div>
                    <a href="{{ route('videos.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900">
                        Retour
                    </a>
                </div>

                <div class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach ($videos as $video)
                        <a href="{{ route('videos.show', $video) }}" class="block rounded-2xl border border-slate-200 overflow-hidden bg-white">
                            <div class="aspect-video bg-slate-100 overflow-hidden flex items-center justify-center">
                                @if (!empty($video->poster_path))
                                    <img src="{{ route('videos.poster', $video) }}" alt="{{ $video->title }}" class="w-full h-full object-cover" loading="lazy" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7 text-slate-400" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path d="M10 9l5 3-5 3V9z" />
                                    </svg>
                                @endif
                            </div>
                            <div class="p-3">
                                <div class="text-sm font-semibold text-gray-900 truncate">{{ $video->title }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $video->created_at?->diffForHumans() }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>

                @if ($videos->hasPages())
                    <div class="mt-6">
                        {{ $videos->links() }}
                    </div>
                @endif
            </div>
        @endif

        <div id="import" class="bg-white rounded-2xl shadow-sm p-6"
            x-data="{
                file: null,
                fileName: '',
                fileSize: '',
                title: '',
                category: '',
                description: '',
                isDragOver: false,
                isUploading: false,
                progress: 0,
                successMessage: '',
                errorMessage: '',
                setFile(f) {
                    if (!f) {
                        this.file = null;
                        this.fileName = '';
                        this.fileSize = 0;
                        this.progress = 0;
                        return;
                    }
                    this.file = f;
                    this.fileName = f.name;
                    this.fileSize = (typeof f.size === 'number') ? f.size : 0;
                },
                setFileFromInput(e) {
                    const f = e?.target?.files?.[0];
                    this.setFile(f);
                },
                setFileFromDrop(e) {
                    const f = e?.dataTransfer?.files?.[0];
                    if (!f) return;
                    if (this.$refs.videoInput) {
                        this.$refs.videoInput.files = e.dataTransfer.files;
                    }
                    this.setFile(f);
                },
                formatBytes(bytes) {
                    bytes = Number(bytes || 0);
                    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
                    const units = ['B','KB','MB','GB'];
                    let i = 0;
                    let v = bytes;
                    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
                    const decimals = i === 0 ? 0 : 1;
                    const str = v.toFixed(decimals).replace(/\.0$/, '');
                    return `${str} ${units[i]}`;
                },
                canSubmit() {
                    return !!this.file && (this.title || '').trim().length > 0 && (this.category || '').trim().length > 0 && !this.isUploading;
                },
                async importVideo() {
                    this.successMessage = '';
                    this.errorMessage = '';
                    if (!this.canSubmit()) return;
                    this.isUploading = true;
                    this.progress = 0;

                    const buildPosterBlob = async (file) => {
                        try {
                            if (!file) return null;
                            if (!String(file.type || '').startsWith('video/')) return null;

                            const url = URL.createObjectURL(file);
                            const video = document.createElement('video');
                            video.preload = 'metadata';
                            video.muted = true;
                            video.playsInline = true;
                            video.src = url;

                            const wait = (eventName, timeoutMs) => new Promise((resolve, reject) => {
                                const t = setTimeout(() => reject(new Error('timeout:' + eventName)), timeoutMs);
                                const on = () => {
                                    clearTimeout(t);
                                    video.removeEventListener(eventName, on);
                                    resolve();
                                };
                                video.addEventListener(eventName, on, { once: true });
                            });

                            await wait('loadedmetadata', 4000);

                            const duration = Number(video.duration || 0);
                            const target = (Number.isFinite(duration) && duration > 2) ? 1 : 0;
                            video.currentTime = target;
                            await wait('seeked', 4000);

                            const w = video.videoWidth || 0;
                            const h = video.videoHeight || 0;
                            if (!w || !h) {
                                URL.revokeObjectURL(url);
                                return null;
                            }

                            const maxW = 640;
                            const scale = Math.min(1, maxW / w);
                            const cw = Math.max(1, Math.round(w * scale));
                            const ch = Math.max(1, Math.round(h * scale));

                            const canvas = document.createElement('canvas');
                            canvas.width = cw;
                            canvas.height = ch;
                            const ctx = canvas.getContext('2d');
                            if (!ctx) {
                                URL.revokeObjectURL(url);
                                return null;
                            }
                            ctx.drawImage(video, 0, 0, cw, ch);

                            const blob = await new Promise((resolve) => {
                                canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
                            });

                            URL.revokeObjectURL(url);
                            return blob;
                        } catch {
                            return null;
                        }
                    };

                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '');
                    formData.append('video_file', this.file);
                    formData.append('title', (this.title || '').trim());
                    formData.append('category', (this.category || '').trim());
                    formData.append('description', (this.description || '').trim());

                    // Auto-poster (thumbnail) without user input.
                    const posterBlob = await buildPosterBlob(this.file);
                    if (posterBlob) {
                        formData.append('poster_file', posterBlob, 'poster.jpg');
                    }

                    await new Promise((resolve) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '{{ route('videos.store') }}', true);
                        xhr.setRequestHeader('Accept', 'application/json');

                        xhr.upload.onprogress = (evt) => {
                            if (!evt.lengthComputable) return;
                            this.progress = Math.round((evt.loaded / evt.total) * 100);
                        };

                        xhr.onload = () => {
                            try {
                                const data = xhr.responseText ? JSON.parse(xhr.responseText) : {};
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    this.successMessage = data?.message || 'Vidéo importée';
                                    this.errorMessage = '';
                                    this.progress = 100;

                                    // Reset minimal fields, keep UX simple.
                                    this.title = '';
                                    this.category = '';
                                    this.description = '';
                                    this.file = null;
                                    this.fileName = '';
                                    this.fileSize = '';
                                    if (this.$refs.videoInput) {
                                        this.$refs.videoInput.value = '';
                                    }
                                } else if (xhr.status === 422) {
                                    const errors = data?.errors || {};
                                    const firstKey = Object.keys(errors)[0];
                                    const first = firstKey && Array.isArray(errors[firstKey]) ? errors[firstKey][0] : null;
                                    this.errorMessage = first || data?.message || 'Erreur de validation. Vérifie les champs.';
                                } else {
                                    this.errorMessage = data?.message || 'Erreur lors de l\'envoi. Réessayer.';
                                }
                            } catch {
                                this.errorMessage = 'Erreur lors de l\'envoi. Réessayer.';
                            } finally {
                                this.isUploading = false;
                                resolve();
                            }
                        };

                        xhr.onerror = () => {
                            this.isUploading = false;
                            this.errorMessage = 'Erreur réseau. Réessayer.';
                            resolve();
                        };

                        xhr.send(formData);
                    });
                },
            }"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-base font-semibold text-gray-900">Importer une vidéo</div>
                    <div class="text-sm text-slate-500 mt-1">Taille max : 3 GB</div>
                </div>
            </div>

            <div class="mt-4">
                <div class="text-sm font-semibold text-gray-900">Dernier upload</div>
                @if (!empty($latestVideo))
                    <div class="mt-2 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center">
                            @if (!empty($latestVideo->poster_path))
                                <img src="{{ route('videos.poster', $latestVideo) }}" alt="{{ $latestVideo->title }}" class="w-full h-full object-cover" loading="lazy" />
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-slate-400" aria-hidden="true">
                                    <rect x="3" y="5" width="18" height="14" rx="2" />
                                    <path d="M10 9l5 3-5 3V9z" />
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $latestVideo->title }}</div>
                            <div class="text-xs text-slate-500">{{ $latestVideo->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                @else
                    <div class="mt-2 text-sm text-slate-500">Aucune vidéo pour l’instant</div>
                @endif
            </div>

            <div class="mt-4">
                <input x-ref="videoInput" type="file" accept="video/*" class="sr-only" x-on:change="setFileFromInput($event)" />

                <div
                    class="border-2 border-dashed border-slate-200 rounded-2xl p-6"
                    :class="isDragOver ? 'bg-slate-50' : 'bg-white'"
                    x-on:dragover.prevent="isDragOver = true"
                    x-on:dragleave.prevent="isDragOver = false"
                    x-on:drop.prevent="isDragOver = false; setFileFromDrop($event)"
                    x-on:click="$refs.videoInput?.click()"
                    role="button"
                    tabindex="0"
                    x-on:keydown.enter.prevent="$refs.videoInput?.click()"
                    x-on:keydown.space.prevent="$refs.videoInput?.click()"
                >
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-900">Glissez-déposez une vidéo ici</div>
                        <div class="text-sm text-slate-500 mt-1">ou</div>
                        <div class="mt-3">
                            <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900" x-on:click.stop="$refs.videoInput?.click()">
                                Choisir un fichier
                            </button>
                        </div>

                        <template x-if="fileName">
                            <div class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-gray-900">
                                <span class="font-medium" x-text="fileName"></span>
                                <span class="text-slate-500" x-text="formatBytes(fileSize)"></span>
                                <button type="button" class="ml-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold" x-on:click.stop="$refs.videoInput?.click()">
                                    Changer
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <template x-if="file">
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-900">Titre *</label>
                        <input type="text" class="mt-2 block w-full rounded-xl border-slate-200" x-model="title" placeholder="Titre" />
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-900">Catégorie *</label>
                        <select class="mt-2 block w-full rounded-xl border-slate-200" x-model="category">
                            <option value="">-- Choisir --</option>
                            <option value="films">Films</option>
                            <option value="series">Séries</option>
                            <option value="docs">Documentaires</option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-900">Description</label>
                        <textarea rows="1" class="mt-2 block w-full rounded-xl border-slate-200" x-model="description" placeholder="Optionnel"></textarea>
                    </div>
                </div>
            </template>

            <template x-if="isUploading">
                <div class="mt-4">
                    <div class="text-sm text-slate-500">Envoi en cours…</div>
                    <div class="mt-2 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full bg-slate-900" :style="`width:${progress}%`"></div>
                    </div>
                </div>
            </template>

            <template x-if="successMessage">
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" x-text="successMessage"></div>
            </template>

            <template x-if="errorMessage">
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div x-text="errorMessage"></div>
                    <button type="button" class="mt-2 rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700" x-on:click="importVideo()">
                        Réessayer
                    </button>
                </div>
            </template>

            <div class="mt-6 flex items-center justify-end">
                <button
                    type="button"
                    class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50"
                    x-bind:disabled="!canSubmit()"
                    x-on:click="importVideo()"
                >
                    Importer la vidéo
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
