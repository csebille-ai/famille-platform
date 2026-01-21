@php
    $initialTab = strtolower((string) ($tab ?? 'photos'));
    if ($initialTab === 'images') {
        $initialTab = 'photos';
    }
    if (!in_array($initialTab, ['photos', 'videos'], true)) {
        $initialTab = 'photos';
    }
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <script type="application/json" id="media-initial-tab">@json($initialTab)</script>
    <script type="application/json" id="media-images-items">@json($imagesItems ?? [])</script>
    <script type="application/json" id="media-videos-items">@json($videosItems ?? [])</script>
    <script type="application/json" id="media-images-next-cursor">@json($imagesNextCursor ?? null)</script>
    <script type="application/json" id="media-videos-next-cursor">@json($videosNextCursor ?? null)</script>

    <div
        class="max-w-6xl mx-auto px-6 pt-4 pb-6 space-y-4"
        x-data="{
            tab: 'photos',
            canImagesUpload: @json(auth()->user()?->can('images-upload') ?? false),
            canCloudWrite: @json(auth()->user()?->can('cloud-write') ?? false),
            pageSize: {{ (int) ($pageSize ?? 24) }},
            photos: [],
            videos: [],
            nextPhotosCursor: null,
            nextVideosCursor: null,
            loadingPhotos: false,
            loadingVideos: false,
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
                // Unified behavior: navigate to the dedicated viewer route
                // so the URL/destination is consistent across the app.
                const i = Number(index);
                if (!Number.isFinite(i)) return;
                if (!Array.isArray(this.photos) || this.photos.length === 0) return;
                const item = this.photos[Math.max(0, Math.min(this.photos.length - 1, i))] || {};
                const url = String(item?.open_url || '').trim();
                if (!url) return;
                window.location.href = url;
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
            }
        }"
    >
        <div class="fam-card p-3 md:p-4">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2.5">
                        <div class="grid grid-cols-2 rounded-xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] p-1 flex-1">
                            <button
                                type="button"
                                class="rounded-lg px-3 text-center text-[0.72rem] font-semibold transition inline-flex items-center justify-center h-11"
                                :class="tab === 'photos' ? 'bg-[color:var(--fam-primary)] text-white shadow-sm' : 'text-slate-700 hover:bg-[color:var(--fam-tint)]'"
                                @click="setTab('photos')"
                                aria-controls="media-photos"
                                :aria-selected="tab === 'photos'"
                                role="tab"
                            >
                                Photos
                            </button>

                            <button
                                type="button"
                                class="rounded-lg px-3 text-center text-[0.72rem] font-semibold transition inline-flex items-center justify-center h-11"
                                :class="tab === 'videos' ? 'bg-[color:var(--fam-primary)] text-white shadow-sm' : 'text-slate-700 hover:bg-[color:var(--fam-tint)]'"
                                @click="setTab('videos')"
                                aria-controls="media-videos"
                                :aria-selected="tab === 'videos'"
                                role="tab"
                            >
                                Vidéos
                            </button>
                        </div>

                        <div class="shrink-0" x-show="(tab === 'photos' && canImagesUpload) || (tab === 'videos' && canCloudWrite)" x-cloak>
                            @can('images-upload')
                                <form
                                    method="POST"
                                    action="{{ route('images.store') }}"
                                    enctype="multipart/form-data"
                                    class="absolute -left-[9999px] top-auto h-px w-px overflow-hidden"
                                >
                                    @csrf
                                    <input
                                        id="media-photos-upload-input"
                                        name="image"
                                        type="file"
                                        accept="image/*"
                                        onchange="this.form.submit()"
                                    />
                                </form>

                                <label
                                    for="media-photos-upload-input"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] text-slate-900 hover:bg-[color:var(--fam-tint)]"
                                    x-show="tab === 'photos'"
                                    aria-label="Ajouter une photo"
                                >
                                    <i class="ph ph-plus" aria-hidden="true"></i>
                                </label>
                            @endcan

                            @can('cloud-write')
                                <button
                                    type="button"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] text-slate-900 hover:bg-[color:var(--fam-tint)]"
                                    aria-label="Ajouter une vidéo"
                                    x-show="tab === 'videos'"
                                    onclick="window.openGlobalUploadPicker && window.openGlobalUploadPicker()"
                                >
                                    <i class="ph ph-plus" aria-hidden="true"></i>
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="media-photos" x-show="tab === 'photos'" x-cloak>
            <template x-if="(photos || []).length === 0">
                    <div class="fam-card p-6">
                    <div class="text-base font-semibold text-gray-900">Aucune photo pour l’instant</div>
                    @can('images-upload')
                        <div class="microcopy text-sm text-slate-500 mt-1">Ajoutez une première photo avec “+ Ajouter”.</div>
                    @endcan
                </div>
            </template>

            <template x-if="(photos || []).length > 0">
                <div>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="(img, idx) in (photos || [])" :key="'photo_' + img.id">
                            <a
                                :href="img.open_url"
                                class="block overflow-hidden rounded-xl bg-[color:var(--fam-surface)] ring-1 ring-black/10 hover:bg-[color:var(--fam-surface-alt)] hover:ring-[color:rgba(14,165,160,0.25)] active:scale-[0.99] transition"
                                :aria-label="'Ouvrir photo ' + (idx + 1)"
                                :data-shared-id="'media:' + img.id"
                                :data-shared-src="img.thumb_url"
                            >
                                <div class="aspect-square bg-[color:var(--fam-surface-alt)]">
                                    <img
                                        :src="img.thumb_url"
                                        alt=""
                                        class="block h-full w-full object-cover"
                                        :style="{ objectPosition: focalPosition(img) }"
                                        loading="lazy"
                                        :data-shared-id="'media:' + img.id"
                                    />
                                </div>
                            </a>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-[color:var(--fam-surface-alt)] disabled:opacity-50"
                            @click="loadMore('photos')"
                            :disabled="!nextPhotosCursor || loadingPhotos"
                            x-show="!!nextPhotosCursor"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span x-show="!loadingPhotos">Charger plus</span>
                                <span x-show="loadingPhotos" class="inline-flex items-center gap-2">
                                    <i class="ph ph-circle-notch animate-spin text-slate-600" style="font-size:16px" aria-hidden="true"></i>
                                    Chargement…
                                </span>
                            </span>
                        </button>
                    </div>

                    <template x-if="loadingPhotos">
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <template x-for="i in Array.from({ length: skeletonCount })" :key="'img_skel_' + i">
                                <div class="overflow-hidden rounded-xl bg-[color:var(--fam-surface)] ring-1 ring-black/10">
                                    <div class="aspect-square bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div id="media-videos" x-show="tab === 'videos'" x-cloak>
            <template x-if="(videos || []).length === 0">
                    <div class="fam-card p-6">
                    <div class="text-base font-semibold text-gray-900">Aucune vidéo pour l’instant</div>
                    @can('cloud-write')
                        <div class="microcopy text-sm text-slate-500 mt-1">Ajoutez une première vidéo avec “+ Ajouter”.</div>
                    @endcan
                </div>
            </template>

            <template x-if="(videos || []).length > 0">
                <div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="v in (videos || [])" :key="'vid_' + v.id">
                            <a :href="v.open_url" class="block rounded-xl overflow-hidden bg-[color:var(--fam-surface)] ring-1 ring-black/10 hover:bg-[color:var(--fam-surface-alt)] hover:ring-[color:rgba(14,165,160,0.25)] transition">
                                <div class="aspect-video bg-[color:var(--fam-surface-alt)] overflow-hidden flex items-center justify-center relative">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Vidéo'" class="block w-full h-full object-cover" :style="{ objectPosition: focalPosition(v) }" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <i class="ph ph-video text-slate-400" style="font-size:28px" aria-hidden="true"></i>
                                    </template>

                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="h-12 w-12 rounded-full bg-black/35 backdrop-blur-sm flex items-center justify-center">
                                            <i class="ph ph-play text-white" aria-hidden="true"></i>
                                        </div>
                                    </div>

                                    <template x-if="!!formatDuration(v.duration_seconds)">
                                        <div class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[0.7rem] font-semibold text-white">
                                            <span x-text="formatDuration(v.duration_seconds)"></span>
                                        </div>
                                    </template>

                                    <div class="absolute inset-x-0 bottom-0 pointer-events-none bg-gradient-to-t from-black/70 via-black/25 to-transparent p-2.5 pt-10">
                                        <div class="text-[0.72rem] font-semibold text-white truncate" x-text="v.title || 'Vidéo'"></div>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-[color:var(--fam-surface-alt)] disabled:opacity-50"
                            @click="loadMore('videos')"
                            :disabled="!nextVideosCursor || loadingVideos"
                            x-show="!!nextVideosCursor"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span x-show="!loadingVideos">Charger plus</span>
                                <span x-show="loadingVideos" class="inline-flex items-center gap-2">
                                    <i class="ph ph-circle-notch animate-spin text-slate-600" style="font-size:16px" aria-hidden="true"></i>
                                    Chargement…
                                </span>
                            </span>
                        </button>
                    </div>

                    <template x-if="loadingVideos">
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <template x-for="i in Array.from({ length: skeletonCount })" :key="'vid_skel_' + i">
                                <div class="rounded-xl overflow-hidden bg-[color:var(--fam-surface)] ring-1 ring-black/10">
                                    <div class="aspect-video bg-[color:var(--fam-surface-alt)] animate-pulse"></div>
                                    <div class="px-2 py-2">
                                        <div class="h-3 w-2/3 bg-[color:var(--fam-surface-alt)] animate-pulse rounded"></div>
                                        <div class="mt-2 h-3 w-1/2 bg-[color:var(--fam-surface-alt)] animate-pulse rounded"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

    </div>

    <script>
        // /media-only: iOS/PWA can sometimes treat `position: fixed` as relative to a transformed ancestor.
        // To avoid breaking other pages, we "portal" the fixed nav elements to <body> only on /media.
        (() => {
            const isMobileViewport = () => {
                try {
                    return !!(window.matchMedia && window.matchMedia('(max-width: 639px)').matches);
                } catch (e) {
                    return true;
                }
            };

            const portalToBody = (el) => {
                if (!el) return;
                if (el.dataset && el.dataset.famPortaled === '1') return;
                try {
                    document.body.appendChild(el);
                    if (el.dataset) el.dataset.famPortaled = '1';
                } catch (e) {
                    // ignore
                }
            };

            const forceFixed = (el, { top = null, bottom = null, zIndex = 50 } = {}) => {
                if (!el) return;
                el.style.position = 'fixed';
                el.style.left = '0';
                el.style.right = '0';
                if (top != null) {
                    el.style.top = String(top);
                    el.style.bottom = '';
                }
                if (bottom != null) {
                    el.style.bottom = String(bottom);
                    el.style.top = '';
                }
                el.style.zIndex = String(zIndex);
                el.style.transform = 'translate3d(0,0,0)';
                el.style.willChange = 'transform';
            };

            const apply = () => {
                // Top navigation: ensure it's fixed at the top.
                const topNav = document.getElementById('appTopNav');
                if (topNav) {
                    portalToBody(topNav);
                    forceFixed(topNav, { top: '0px', zIndex: 50 });
                }

                // Bottom navigation (mobile only): ensure it's fixed at the bottom.
                if (isMobileViewport()) {
                    const bottomNav = document.querySelector('nav[aria-label="Navigation principale"]');
                    if (bottomNav) {
                        portalToBody(bottomNav);
                        forceFixed(bottomNav, { bottom: '0px', zIndex: 50 });
                    }
                }
            };

            const schedule = () => requestAnimationFrame(() => requestAnimationFrame(apply));

            window.addEventListener('pageshow', schedule);
            window.addEventListener('load', schedule, { once: true });
            window.addEventListener('resize', schedule, { passive: true });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') schedule();
            });

            schedule();
        })();
    </script>
</x-app-layout>
