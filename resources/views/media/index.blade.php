@php
    $initialTab = strtolower((string) ($tab ?? 'photos'));
    if ($initialTab === 'images') {
        $initialTab = 'photos';
    }
    if (!in_array($initialTab, ['photos', 'videos'], true)) {
        $initialTab = 'photos';
    }
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <script type="application/json" id="media-initial-tab">@json($initialTab)</script>
    <script type="application/json" id="media-images-items">@json($imagesItems ?? [])</script>
    <script type="application/json" id="media-videos-items">@json($videosItems ?? [])</script>
    <script type="application/json" id="media-images-next-cursor">@json($imagesNextCursor ?? null)</script>
    <script type="application/json" id="media-videos-next-cursor">@json($videosNextCursor ?? null)</script>

    <div
        class="max-w-6xl mx-auto px-6 py-6 space-y-4"
        x-data="{
            tab: 'photos',
            pageSize: {{ (int) ($pageSize ?? 24) }},
            photos: [],
            videos: [],
            nextPhotosCursor: null,
            nextVideosCursor: null,
            loadingPhotos: false,
            loadingVideos: false,
            skeletonCount: 12,
            viewerOpen: false,
            viewerIndex: 0,
            touchStartX: null,
            touchStartY: null,
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
                if (v === 'images') v = 'photos';
                return (v === 'videos') ? 'videos' : 'photos';
            },
            focalPosition(item) {
                const defX = 50;
                const defY = 35;
                const fxRaw = item?.focal_x;
                const fyRaw = item?.focal_y;
                const fx = Number(fxRaw);
                const fy = Number(fyRaw);
                if (!Number.isFinite(fx) || !Number.isFinite(fy)) {
                    return `${defX}% ${defY}%`;
                }
                const clamp01 = (n) => Math.max(0, Math.min(1, n));
                const x = Math.round(clamp01(fx) * 1000) / 10;
                const y = Math.round(clamp01(fy) * 1000) / 10;
                return `${x}% ${y}%`;
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
            openViewer(index) {
                const i = Number(index);
                if (!Number.isFinite(i)) return;
                if (!Array.isArray(this.photos) || this.photos.length === 0) return;
                this.viewerIndex = Math.max(0, Math.min(this.photos.length - 1, i));
                this.viewerOpen = true;
                try { document.body.style.overflow = 'hidden'; } catch (e) {}
            },
            closeViewer() {
                this.viewerOpen = false;
                try { document.body.style.overflow = ''; } catch (e) {}
            },
            nextPhoto() {
                if (!Array.isArray(this.photos) || this.photos.length === 0) return;
                this.viewerIndex = (this.viewerIndex + 1) % this.photos.length;
            },
            prevPhoto() {
                if (!Array.isArray(this.photos) || this.photos.length === 0) return;
                this.viewerIndex = (this.viewerIndex - 1 + this.photos.length) % this.photos.length;
            },
            onTouchStart(e) {
                const t = e?.touches?.[0];
                if (!t) return;
                this.touchStartX = t.clientX;
                this.touchStartY = t.clientY;
            },
            onTouchEnd(e) {
                const t = e?.changedTouches?.[0];
                if (!t || this.touchStartX === null || this.touchStartY === null) return;
                const dx = t.clientX - this.touchStartX;
                const dy = t.clientY - this.touchStartY;
                this.touchStartX = null;
                this.touchStartY = null;
                if (Math.abs(dx) < 50) return;
                if (Math.abs(dx) <= Math.abs(dy)) return;
                if (dx < 0) this.nextPhoto();
                else this.prevPhoto();
            },
            async loadMore(type) {
                const isPhotos = (type === 'photos');
                if (isPhotos) {
                    if (!this.nextPhotosCursor || this.loadingPhotos) return;
                    this.loadingPhotos = true;
                } else {
                    if (!this.nextVideosCursor || this.loadingVideos) return;
                    this.loadingVideos = true;
                }

                try {
                    const url = new URL(window.location.origin + '/api/media');
                    url.searchParams.set('type', isPhotos ? 'image' : 'video');
                    url.searchParams.set('limit', String(this.pageSize || 24));
                    url.searchParams.set('cursor', isPhotos ? this.nextPhotosCursor : this.nextVideosCursor);

                    // Preserve any future filters/search from the current page URL.
                    const pageUrl = new URL(window.location.href);
                    pageUrl.searchParams.forEach((value, key) => {
                        if (['tab', 'cursor', 'limit', 'type'].includes(key)) return;
                        url.searchParams.set(key, value);
                    });

                    const res = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!res.ok) throw new Error('http:' + res.status);
                    const data = await res.json();
                    const items = Array.isArray(data?.items) ? data.items : [];
                    const next = data?.next_cursor || null;

                    if (isPhotos) {
                        this.photos = (this.photos || []).concat(items);
                        this.nextPhotosCursor = next;
                    } else {
                        this.videos = (this.videos || []).concat(items);
                        this.nextVideosCursor = next;
                    }
                } catch (e) {
                    // Keep it silent for now; button will re-enable.
                } finally {
                    if (isPhotos) this.loadingPhotos = false;
                    else this.loadingVideos = false;
                }
            },
            init() {
                const initialTab = this.normalize(this.readJson('media-initial-tab') || 'photos');
                this.tab = initialTab;
                this.photos = this.readJson('media-images-items') || [];
                this.videos = this.readJson('media-videos-items') || [];
                this.nextPhotosCursor = this.readJson('media-images-next-cursor');
                this.nextVideosCursor = this.readJson('media-videos-next-cursor');

                this.tab = this.normalize(this.readFromUrl() || initialTab);
                this.writeToUrl(false);

                window.addEventListener('popstate', () => {
                    this.tab = this.readFromUrl();
                });
                window.addEventListener('hashchange', () => {
                    this.tab = this.readFromUrl();
                });

                window.addEventListener('keydown', (e) => {
                    if (!this.viewerOpen) return;
                    if (e.key === 'Escape') this.closeViewer();
                    if (e.key === 'ArrowRight') this.nextPhoto();
                    if (e.key === 'ArrowLeft') this.prevPhoto();
                });
            }
        }"
    >
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Médias</h1>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-3 md:p-4">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="grid grid-cols-2 rounded-xl border border-slate-200 bg-white p-1">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-center text-[0.72rem] font-semibold transition"
                            :class="tab === 'photos' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50'"
                            @click="setTab('photos')"
                            aria-controls="media-photos"
                            :aria-selected="tab === 'photos'"
                            role="tab"
                        >
                            Photos
                        </button>

                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-center text-[0.72rem] font-semibold transition"
                            :class="tab === 'videos' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50'"
                            @click="setTab('videos')"
                            aria-controls="media-videos"
                            :aria-selected="tab === 'videos'"
                            role="tab"
                        >
                            Vidéos
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-900 hover:bg-slate-50"
                    @click="$dispatch('open-add')"
                    aria-haspopup="dialog"
                    aria-label="Ajouter"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
                        <path d="M12 5v14" />
                        <path d="M5 12h14" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="media-photos" x-show="tab === 'photos'" x-cloak>
            <template x-if="(photos || []).length === 0">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Aucune photo pour l’instant</div>
                    <div class="text-sm text-slate-500 mt-1">Ajoutez une première photo avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(photos || []).length > 0">
                <div>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="(img, idx) in (photos || [])" :key="'photo_' + img.id">
                            <button
                                type="button"
                                class="block overflow-hidden rounded-xl bg-slate-100"
                                @click="openViewer(idx)"
                                :aria-label="'Ouvrir photo ' + (idx + 1)"
                            >
                                <div class="aspect-square">
                                    <img :src="img.thumb_url" alt="" class="block h-full w-full object-cover" :style="{ objectPosition: focalPosition(img) }" loading="lazy" />
                                </div>
                            </button>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900 disabled:opacity-50"
                            @click="loadMore('photos')"
                            :disabled="!nextPhotosCursor || loadingPhotos"
                            x-show="!!nextPhotosCursor"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span x-show="!loadingPhotos">Charger plus</span>
                                <span x-show="loadingPhotos" class="inline-flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin text-slate-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                    </svg>
                                    Chargement…
                                </span>
                            </span>
                        </button>
                    </div>

                    <template x-if="loadingPhotos">
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <template x-for="i in Array.from({ length: skeletonCount })" :key="'img_skel_' + i">
                                <div class="overflow-hidden rounded-xl bg-slate-100">
                                    <div class="aspect-square bg-slate-200/60 animate-pulse"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div id="media-videos" x-show="tab === 'videos'" x-cloak>
            <template x-if="(videos || []).length === 0">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Aucune vidéo pour l’instant</div>
                    <div class="text-sm text-slate-500 mt-1">Ajoutez une première vidéo avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(videos || []).length > 0">
                <div>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="v in (videos || [])" :key="'vid_' + v.id">
                            <a :href="v.open_url" class="block rounded-xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-video bg-slate-100 overflow-hidden flex items-center justify-center relative">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Vidéo'" class="block w-full h-full object-cover" :style="{ objectPosition: focalPosition(v) }" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7 text-slate-400" aria-hidden="true">
                                            <rect x="3" y="5" width="18" height="14" rx="2" />
                                            <path d="M10 9l5 3-5 3V9z" />
                                        </svg>
                                    </template>

                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="h-12 w-12 rounded-full bg-black/35 backdrop-blur-sm flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6 text-white" aria-hidden="true">
                                                <path d="M8 5v14l11-7z" />
                                            </svg>
                                        </div>
                                    </div>

                                    <template x-if="!!formatDuration(v.duration_seconds)">
                                        <div class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[0.7rem] font-semibold text-white">
                                            <span x-text="formatDuration(v.duration_seconds)"></span>
                                        </div>
                                    </template>

                                    <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/70 via-black/25 to-transparent pointer-events-none"></div>
                                    <div class="absolute inset-x-0 bottom-0 p-2 pr-12 pointer-events-none">
                                        <div class="text-[0.72rem] font-semibold text-white truncate" x-text="v.title || 'Vidéo'"></div>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900 disabled:opacity-50"
                            @click="loadMore('videos')"
                            :disabled="!nextVideosCursor || loadingVideos"
                            x-show="!!nextVideosCursor"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span x-show="!loadingVideos">Charger plus</span>
                                <span x-show="loadingVideos" class="inline-flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin text-slate-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                    </svg>
                                    Chargement…
                                </span>
                            </span>
                        </button>
                    </div>

                    <template x-if="loadingVideos">
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <template x-for="i in Array.from({ length: skeletonCount })" :key="'vid_skel_' + i">
                                <div class="rounded-xl overflow-hidden bg-white shadow-sm">
                                    <div class="aspect-video bg-slate-100 animate-pulse"></div>
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

        <!-- Fullscreen photo viewer -->
        <div x-show="viewerOpen" x-cloak class="fixed inset-0 z-50" aria-modal="true" role="dialog">
            <button type="button" class="absolute inset-0 bg-black" @click="closeViewer()" aria-label="Fermer"></button>

            <div
                class="absolute inset-0 flex items-center justify-center"
                @touchstart.passive="onTouchStart($event)"
                @touchend.passive="onTouchEnd($event)"
            >
                <template x-if="(photos || []).length">
                    <img
                        :src="(photos[viewerIndex] || {}).thumb_url"
                        alt=""
                        class="max-h-full max-w-full object-contain"
                        @click.stop
                    />
                </template>

                <button
                    type="button"
                    class="absolute top-4 right-4 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white"
                    @click.stop="closeViewer()"
                    aria-label="Fermer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
                        <path d="M18 6L6 18" />
                        <path d="M6 6l12 12" />
                    </svg>
                </button>

                <button
                    type="button"
                    class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white"
                    @click.stop="prevPhoto()"
                    aria-label="Précédent"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white"
                    @click.stop="nextPhoto()"
                    aria-label="Suivant"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
