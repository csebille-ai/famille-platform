@php
    $initialTab = strtolower((string) ($tab ?? 'images'));
    if (!in_array($initialTab, ['images', 'videos'], true)) {
        $initialTab = 'images';
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
            tab: 'images',
            pageSize: {{ (int) ($pageSize ?? 24) }},
            images: [],
            videos: [],
            nextImagesCursor: null,
            nextVideosCursor: null,
            loadingImages: false,
            loadingVideos: false,
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
            normalize(v) {
                v = String(v || '').toLowerCase().trim();
                return (v === 'videos') ? 'videos' : 'images';
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
            canLoadMore(type) {
                if (type === 'images') return !!this.nextImagesCursor && !this.loadingImages;
                return !!this.nextVideosCursor && !this.loadingVideos;
            },
            async loadMore(type) {
                const isImages = (type === 'images');
                if (isImages) {
                    if (!this.nextImagesCursor || this.loadingImages) return;
                    this.loadingImages = true;
                } else {
                    if (!this.nextVideosCursor || this.loadingVideos) return;
                    this.loadingVideos = true;
                }

                try {
                    const url = new URL(window.location.origin + '/api/media');
                    url.searchParams.set('type', isImages ? 'image' : 'video');
                    url.searchParams.set('limit', String(this.pageSize || 24));
                    url.searchParams.set('cursor', isImages ? this.nextImagesCursor : this.nextVideosCursor);

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

                    if (isImages) {
                        this.images = (this.images || []).concat(items);
                        this.nextImagesCursor = next;
                    } else {
                        this.videos = (this.videos || []).concat(items);
                        this.nextVideosCursor = next;
                    }
                } catch (e) {
                    // Keep it silent for now; button will re-enable.
                } finally {
                    if (isImages) this.loadingImages = false;
                    else this.loadingVideos = false;
                }
            },
            init() {
                const initialTab = this.normalize(this.readJson('media-initial-tab') || 'images');
                this.tab = initialTab;
                this.images = this.readJson('media-images-items') || [];
                this.videos = this.readJson('media-videos-items') || [];
                this.nextImagesCursor = this.readJson('media-images-next-cursor');
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
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Médias</h1>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-3 md:p-4">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="tab === 'images' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                    @click="setTab('images')"
                    aria-controls="media-images"
                    :aria-selected="tab === 'images'"
                    role="tab"
                >
                    Images
                </button>

                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="tab === 'videos' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                    @click="setTab('videos')"
                    aria-controls="media-videos"
                    :aria-selected="tab === 'videos'"
                    role="tab"
                >
                    Vidéos
                </button>
            </div>
        </div>

        <div id="media-images" x-show="tab === 'images'" x-cloak>
            <template x-if="(images || []).length === 0">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Aucune image pour l’instant</div>
                    <div class="text-sm text-slate-500 mt-1">Ajoutez une première photo avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(images || []).length > 0">
                <div>
                    <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        <template x-for="img in (images || [])" :key="'img_' + img.id">
                            <a :href="img.open_url" class="block rounded-2xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-[4/3] bg-slate-100">
                                    <img :src="img.thumb_url" :alt="img.name || 'Photo'" class="w-full h-full object-cover" loading="lazy" />
                                </div>
                                <div class="p-3">
                                    <div class="text-xs text-slate-500 truncate">
                                        <span x-text="img.by || 'Quelqu\u2019un'"></span>
                                        <span class="text-slate-400">·</span>
                                        <span x-text="img.at_human || ''"></span>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900 disabled:opacity-50"
                            @click="loadMore('images')"
                            :disabled="!nextImagesCursor || loadingImages"
                            x-show="!!nextImagesCursor"
                        >
                            <span x-show="!loadingImages">Charger plus</span>
                            <span x-show="loadingImages">Chargement…</span>
                        </button>
                    </div>
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
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <template x-for="v in (videos || [])" :key="'vid_' + v.id">
                            <a :href="v.open_url" class="block rounded-2xl border border-slate-200 overflow-hidden bg-white">
                                <div class="aspect-video bg-slate-100 overflow-hidden flex items-center justify-center">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Vidéo'" class="w-full h-full object-cover" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7 text-slate-400" aria-hidden="true">
                                            <rect x="3" y="5" width="18" height="14" rx="2" />
                                            <path d="M10 9l5 3-5 3V9z" />
                                        </svg>
                                    </template>
                                </div>
                                <div class="p-3">
                                    <div class="text-sm font-semibold text-gray-900 truncate" x-text="v.title || 'Vidéo'"></div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        <span x-text="v.by || 'Quelqu\u2019un'"></span>
                                        <span class="text-slate-400">·</span>
                                        <span x-text="v.at_human || ''"></span>
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
                            <span x-show="!loadingVideos">Charger plus</span>
                            <span x-show="loadingVideos">Chargement…</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
