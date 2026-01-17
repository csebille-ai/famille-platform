@php
    $initialTab = strtolower((string) ($tab ?? 'films'));
    if (!in_array($initialTab, ['films', 'series'], true)) {
        $initialTab = 'films';
    }
@endphp

<x-app-layout pageBgClass="fam-page-bg">
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
            async loadMore(which) {
                const isFilms = (which === 'films');
                if (isFilms) {
                    if (!this.nextFilmsCursor || this.loadingFilms) return;
                    this.loadingFilms = true;
                } else {
                    if (!this.nextSeriesCursor || this.loadingSeries) return;
                    this.loadingSeries = true;
                }

                try {
                    const url = new URL(window.location.origin + '/api/media');
                    url.searchParams.set('type', 'video');
                    url.searchParams.set('category', isFilms ? 'films' : 'series');
                    url.searchParams.set('limit', String(this.pageSize || 24));
                    url.searchParams.set('cursor', isFilms ? this.nextFilmsCursor : this.nextSeriesCursor);

                    const res = await fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) throw new Error('http:' + res.status);
                    const data = await res.json();
                    const items = Array.isArray(data?.items) ? data.items : [];
                    const next = data?.next_cursor || null;

                    if (isFilms) {
                        this.films = (this.films || []).concat(items);
                        this.nextFilmsCursor = next;
                    } else {
                        this.series = (this.series || []).concat(items);
                        this.nextSeriesCursor = next;
                    }
                } catch (e) {
                    // Silent for now.
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
        <div class="bg-white rounded-2xl shadow-sm p-3 md:p-4">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2.5">
                        <div class="grid grid-cols-2 rounded-xl border border-slate-200 bg-white p-1 flex-1">
                            <button
                                type="button"
                                class="rounded-lg px-3 text-center text-[0.72rem] font-semibold transition inline-flex items-center justify-center h-11"
                                :class="tab === 'films' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]'"
                                @click="setTab('films')"
                                aria-controls="mediatheque-films"
                                :aria-selected="tab === 'films'"
                                role="tab"
                            >
                                Films
                            </button>

                            <button
                                type="button"
                                class="rounded-lg px-3 text-center text-[0.72rem] font-semibold transition inline-flex items-center justify-center h-11"
                                :class="tab === 'series' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]'"
                                @click="setTab('series')"
                                aria-controls="mediatheque-series"
                                :aria-selected="tab === 'series'"
                                role="tab"
                            >
                                Séries
                            </button>
                        </div>

                        @can('cloud-write')
                        <div class="shrink-0">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-black/10 bg-white text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]"
                                aria-label="Ajouter"
                                onclick="window.openGlobalUploadPicker && window.openGlobalUploadPicker()"
                            >
                                <i class="ph ph-plus" aria-hidden="true"></i>
                            </button>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div id="mediatheque-films" x-show="tab === 'films'" x-cloak>
            <template x-if="(films || []).length === 0">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Aucun film pour l’instant</div>
                    <div class="microcopy text-sm text-slate-500 mt-1">Ajoutez un premier film avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(films || []).length > 0">
                <div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="v in (films || [])" :key="'film_' + v.id">
                            <a :href="v.open_url" class="block rounded-xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-[2/3] bg-slate-100 overflow-hidden flex items-center justify-center relative">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Film'" class="block w-full h-full object-cover" :style="{ objectPosition: focalPosition(v) }" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <i class="ph ph-film-strip text-slate-400" style="font-size:28px" aria-hidden="true"></i>
                                    </template>

                                    <template x-if="!!formatDuration(v.duration_seconds)">
                                        <div class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[0.7rem] font-semibold text-white">
                                            <span x-text="formatDuration(v.duration_seconds)"></span>
                                        </div>
                                    </template>

                                    <div class="absolute inset-x-0 bottom-0 pointer-events-none bg-gradient-to-t from-black/75 via-black/20 to-transparent p-2.5 pt-10">
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
                            @click="loadMore('films')"
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
                    <div class="microcopy text-sm text-slate-500 mt-1">Ajoutez une première série avec “+ Ajouter”.</div>
                </div>
            </template>

            <template x-if="(series || []).length > 0">
                <div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="v in (series || [])" :key="'series_' + v.id">
                            <a :href="v.open_url" class="block rounded-xl overflow-hidden bg-white shadow-sm">
                                <div class="aspect-[2/3] bg-slate-100 overflow-hidden flex items-center justify-center relative">
                                    <template x-if="!!v.poster_url">
                                        <img :src="v.poster_url" :alt="v.title || 'Série'" class="block w-full h-full object-cover" :style="{ objectPosition: focalPosition(v) }" loading="lazy" />
                                    </template>
                                    <template x-if="!v.poster_url">
                                        <i class="ph ph-television text-slate-400" style="font-size:28px" aria-hidden="true"></i>
                                    </template>

                                    <template x-if="!!formatDuration(v.duration_seconds)">
                                        <div class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[0.7rem] font-semibold text-white">
                                            <span x-text="formatDuration(v.duration_seconds)"></span>
                                        </div>
                                    </template>

                                    <div class="absolute inset-x-0 bottom-0 pointer-events-none bg-gradient-to-t from-black/75 via-black/20 to-transparent p-2.5 pt-10">
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
                            @click="loadMore('series')"
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
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
