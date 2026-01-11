@php
    $initialTab = strtolower((string) ($tab ?? 'films'));
    if (!in_array($initialTab, ['films', 'series'], true)) {
        $initialTab = 'films';
    }
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <script type="application/json" id="mediatheque-initial-tab">@json($initialTab)</script>
    <script type="application/json" id="mediatheque-films-items">@json($filmsItems ?? [])</script>
    <script type="application/json" id="mediatheque-series-items">@json($seriesItems ?? [])</script>
    <script type="application/json" id="mediatheque-films-next-cursor">@json($filmsNextCursor ?? null)</script>
    <script type="application/json" id="mediatheque-series-next-cursor">@json($seriesNextCursor ?? null)</script>

    <div
        class="max-w-6xl mx-auto px-6 pt-4 pb-6 space-y-4"
        x-data="{
            tab: 'films',
            pageSize: {{ (int) ($pageSize ?? 24) }},
            films: [],
            series: [],
            nextFilmsCursor: null,
            nextSeriesCursor: null,
            loadingFilms: false,
            loadingSeries: false,
            skeletonCount: 12,
            readJson(id) {
                try {
                    const el = document.getElementById(id);
                    if (!el) return null;
                    const txt = (el.textContent || '').trim();
                    if (!txt) return null;
                    return JSON.parse(txt);
                } catch (e) {
                    return null;
                }
            },
            formatDuration(seconds) {
                const s = Number(seconds || 0);
                if (!Number.isFinite(s) || s <= 0) return '';
                const sec = Math.round(s);
                const h = Math.floor(sec / 3600);
                const m = Math.floor((sec % 3600) / 60);
                const r = sec % 60;
                if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
                return `${m}:${String(r).padStart(2, '0')}`;
            },
            normalize(v) {
                v = String(v || '').toLowerCase().trim();
                return (v === 'series') ? 'series' : 'films';
            },
            readFromUrl() {
                const url = new URL(window.location.href);
                const qp = url.searchParams.get('tab');
                const hash = (window.location.hash || '').replace('#', '');
                return this.normalize(qp || hash || this.tab);
            },
            writeToUrl(push) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', this.tab);
                url.hash = this.tab;
                if (push) {
                    window.history.pushState({ tab: this.tab }, '', url);
                } else {
                    window.history.replaceState({ tab: this.tab }, '', url);
                }
            },
            setTab(next) {
                this.tab = this.normalize(next);
                this.writeToUrl(true);
            },
            async loadMore(type) {
                const isFilms = (type === 'films');
                if (isFilms) {
                    if (!this.nextFilmsCursor || this.loadingFilms) return;
                    this.loadingFilms = true;
                } else {
                    if (!this.nextSeriesCursor || this.loadingSeries) return;
                    this.loadingSeries = true;
                }

                const cursor = isFilms ? this.nextFilmsCursor : this.nextSeriesCursor;

                try {
                    const params = new URLSearchParams();
                    params.set('type', 'video');
                    params.set('category', isFilms ? 'films' : 'series');
                    params.set('limit', String(this.pageSize || 24));
                    params.set('cursor', String(cursor || ''));

                    const res = await fetch(`/api/media?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error('bad_response');
                    const data = await res.json();
                    const items = Array.isArray(data.items) ? data.items : [];
                    const nextCursor = data.next_cursor || null;

                    if (isFilms) {
                        this.films = [...(this.films || []), ...items];
                        this.nextFilmsCursor = nextCursor;
                    } else {
                        this.series = [...(this.series || []), ...items];
                        this.nextSeriesCursor = nextCursor;
                    }
                } catch (e) {
                    // noop
                } finally {
                    if (isFilms) this.loadingFilms = false;
                    else this.loadingSeries = false;
                }
            },
            init() {
                const initialTab = this.normalize(this.readJson('mediatheque-initial-tab') || 'films');
                this.tab = initialTab;
                this.films = this.readJson('mediatheque-films-items') || [];
                this.series = this.readJson('mediatheque-series-items') || [];
                this.nextFilmsCursor = this.readJson('mediatheque-films-next-cursor');
                this.nextSeriesCursor = this.readJson('mediatheque-series-next-cursor');

                this.tab = this.normalize(this.readFromUrl() || initialTab);
                this.writeToUrl(false);

                window.addEventListener('popstate', () => {
                    this.tab = this.readFromUrl();
                });
                window.addEventListener('hashchange', () => {
                    this.tab = this.readFromUrl();
                });
            }
        }"
    >
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

        <div class="bg-white rounded-2xl shadow-sm p-3 md:p-4">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2.5">
                        <div class="grid grid-cols-2 rounded-xl border border-slate-200 bg-white p-1 flex-1">
                            <button
                                type="button"
                                class="rounded-lg px-3 text-center text-[0.72rem] font-semibold transition inline-flex items-center justify-center h-11"
                                :class="tab === 'films' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50'"
                                x-on:click="setTab('films')"
                                aria-controls="mediatheque-films"
                                :aria-selected="tab === 'films'"
                                role="tab"
                            >
                                Films
                            </button>

                            <button
                                type="button"
                                class="rounded-lg px-3 text-center text-[0.72rem] font-semibold transition inline-flex items-center justify-center h-11"
                                :class="tab === 'series' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50'"
                                x-on:click="setTab('series')"
                                aria-controls="mediatheque-series"
                                :aria-selected="tab === 'series'"
                                role="tab"
                            >
                                Séries
                            </button>
                        </div>

                        <div class="shrink-0 relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-900 hover:bg-slate-50"
                                aria-label="Ajouter"
                                x-on:click="open = !open"
                            >
                                <i class="ph ph-plus" aria-hidden="true"></i>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-on:click.outside="open = false"
                                class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white shadow-lg p-1"
                            >
                                <a
                                    href="{{ route('videos.create', ['category' => 'films', 'return' => route('mediatheque.index', ['tab' => 'films'])]) }}"
                                    class="block w-full rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50"
                                    x-on:click="open = false"
                                >
                                    Ajouter un film
                                </a>
                                <a
                                    href="{{ route('videos.create', ['category' => 'series', 'return' => route('mediatheque.index', ['tab' => 'series'])]) }}"
                                    class="block w-full rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50"
                                    x-on:click="open = false"
                                >
                                    Ajouter une série
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="mediatheque-films" x-show="tab === 'films'" x-cloak>
            <template x-if="(films || []).length === 0">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Aucun film pour l’instant</div>
                    <div class="text-sm text-slate-500 mt-1">Ajoutez un premier film avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(films || []).length > 0">
                <div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="v in (films || [])" :key="'film_' + v.id">
                            <a :href="v.open_url" class="block rounded-xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-[2/3] bg-slate-100 overflow-hidden flex items-center justify-center relative">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Film'" class="block w-full h-full object-cover" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <i class="ph ph-film-slate text-slate-400" style="font-size:28px" aria-hidden="true"></i>
                                    </template>

                                    <template x-if="!!formatDuration(v.duration_seconds)">
                                        <div class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[0.7rem] font-semibold text-white">
                                            <span x-text="formatDuration(v.duration_seconds)"></span>
                                        </div>
                                    </template>

                                    <div class="absolute inset-x-0 bottom-0 pointer-events-none bg-gradient-to-t from-black/70 via-black/25 to-transparent p-2.5 pt-10">
                                        <div class="text-[0.72rem] font-semibold text-white truncate" x-text="v.title || 'Film'"></div>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900 disabled:opacity-50"
                            x-on:click="loadMore('films')"
                            :disabled="!nextFilmsCursor || loadingFilms"
                            x-show="!!nextFilmsCursor"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span x-show="!loadingFilms">Charger plus</span>
                                <span x-show="loadingFilms" class="inline-flex items-center gap-2">
                                    <i class="ph ph-circle-notch animate-spin text-slate-600" style="font-size:16px" aria-hidden="true"></i>
                                    Chargement…
                                </span>
                            </span>
                        </button>
                    </div>

                    <template x-if="loadingFilms">
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <template x-for="i in Array.from({ length: skeletonCount })" :key="'film_skel_' + i">
                                <div class="rounded-xl overflow-hidden bg-white shadow-sm">
                                    <div class="aspect-[2/3] bg-slate-100 animate-pulse"></div>
                                    <div class="px-2 py-2">
                                        <div class="h-3 w-2/3 bg-slate-100 animate-pulse rounded"></div>
                                        <div class="mt-2 h-3 w-1/2 bg-slate-100 animate-pulse rounded"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div id="mediatheque-series" x-show="tab === 'series'" x-cloak>
            <template x-if="(series || []).length === 0">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Aucune série pour l’instant</div>
                    <div class="text-sm text-slate-500 mt-1">Ajoutez une première série avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(series || []).length > 0">
                <div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="v in (series || [])" :key="'series_' + v.id">
                            <a :href="v.open_url" class="block rounded-xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-[2/3] bg-slate-100 overflow-hidden flex items-center justify-center relative">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Série'" class="block w-full h-full object-cover" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <i class="ph ph-film-slate text-slate-400" style="font-size:28px" aria-hidden="true"></i>
                                    </template>

                                    <template x-if="!!formatDuration(v.duration_seconds)">
                                        <div class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[0.7rem] font-semibold text-white">
                                            <span x-text="formatDuration(v.duration_seconds)"></span>
                                        </div>
                                    </template>

                                    <div class="absolute inset-x-0 bottom-0 pointer-events-none bg-gradient-to-t from-black/70 via-black/25 to-transparent p-2.5 pt-10">
                                        <div class="text-[0.72rem] font-semibold text-white truncate" x-text="v.title || 'Série'"></div>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900 disabled:opacity-50"
                            x-on:click="loadMore('series')"
                            :disabled="!nextSeriesCursor || loadingSeries"
                            x-show="!!nextSeriesCursor"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span x-show="!loadingSeries">Charger plus</span>
                                <span x-show="loadingSeries" class="inline-flex items-center gap-2">
                                    <i class="ph ph-circle-notch animate-spin text-slate-600" style="font-size:16px" aria-hidden="true"></i>
                                    Chargement…
                                </span>
                            </span>
                        </button>
                    </div>

                    <template x-if="loadingSeries">
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <template x-for="i in Array.from({ length: skeletonCount })" :key="'series_skel_' + i">
                                <div class="rounded-xl overflow-hidden bg-white shadow-sm">
                                    <div class="aspect-[2/3] bg-slate-100 animate-pulse"></div>
                                    <div class="px-2 py-2">
                                        <div class="h-3 w-2/3 bg-slate-100 animate-pulse rounded"></div>
                                        <div class="mt-2 h-3 w-1/2 bg-slate-100 animate-pulse rounded"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
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

                            const posterBlob = await buildPosterBlob(this.file);
                            if (posterBlob) {
                                formData.append('poster_file', posterBlob, 'poster.jpg');
                            }

                            await new Promise((resolve) => {
                                const xhr = new XMLHttpRequest();
                                xhr.open('POST', '{{ route('videos.store') }}', true);
                                xhr.setRequestHeader('Accept', 'application/json');
                                xhr.upload.onprogress = (e) => {
                                    if (e.lengthComputable) {
                                        this.progress = Math.round((e.loaded / e.total) * 100);
                                    }
                                };
                                xhr.onload = () => {
                                    this.isUploading = false;
                                    if (xhr.status >= 200 && xhr.status < 300) {
                                        this.successMessage = 'Vidéo importée !';
                                        this.errorMessage = '';
                                        this.setFile(null);
                                        this.title = '';
                                        this.description = '';
                                        resolve();
                                        return;
                                    }

                                    this.errorMessage = 'Erreur pendant l\'import. Réessayer.';
                                    resolve();
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
                    x-on:open-library-import.window="openWith($event.detail?.category)"
                    x-on:keydown.escape.window="if (open) close()"
                >
                    <div x-show="open" x-cloak class="fixed inset-0 z-50" aria-modal="true" role="dialog">
                        <button type="button" class="absolute inset-0 bg-black/50" x-on:click="close()" aria-label="Fermer"></button>

                        <div class="relative mx-auto max-w-2xl px-6 py-10">
                            <div class="bg-white rounded-2xl shadow-sm p-6" x-on:click.stop>
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-base font-semibold text-gray-900">Ajouter à la médiathèque</div>
                                        <div class="text-sm text-slate-500 mt-1">Importer un film ou une série</div>
                                    </div>
                                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-900 hover:bg-slate-50" x-on:click="close()" aria-label="Fermer">
                                        <i class="ph ph-x" aria-hidden="true"></i>
                                    </button>
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

                                            <template x-if="file">
                                                <div class="mt-4 text-left rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                                    <div class="text-sm font-semibold text-gray-900 truncate" x-text="fileName"></div>
                                                    <div class="text-sm text-slate-500 mt-1" x-text="formatBytes(fileSize)"></div>
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
                    </div>
                </div>
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
