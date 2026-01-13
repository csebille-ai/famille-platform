@php
    $chips = [
        ['key' => '', 'label' => 'Tout'],
        ['key' => 'commune', 'label' => 'Commune'],
        ['key' => 'culture', 'label' => 'Culture'],
        ['key' => 'travaux', 'label' => 'Travaux'],
        ['key' => 'sport', 'label' => 'Sport'],
        ['key' => 'meteo', 'label' => 'Météo'],
        ['key' => 'securite', 'label' => 'Sécurité'],
    ];
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-3xl mx-auto">
        <div class="sticky top-16 z-40 bg-slate-50/95 backdrop-blur border-b border-slate-200">
            <div class="px-6 pt-4 pb-3">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold text-gray-900">Actu locale</h1>
                        <div class="mt-1 text-sm text-slate-500">Zone: Local</div>
                        <div id="actu-last" class="mt-1 text-xs text-slate-500"></div>
                        <div id="actu-stale" class="hidden mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Actu possiblement bloquée (pas de synchro récente).
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            id="actu-refresh"
                            class="inline-flex items-center h-10 px-3 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-gray-900 hover:bg-slate-50"
                        >
                            Actualiser
                        </button>
                        @if(auth()->check() && auth()->user()?->can('manage-users') === true)
                            <button
                                type="button"
                                id="actu-sync"
                                class="inline-flex items-center h-10 px-3 rounded-xl border border-indigo-200 bg-indigo-50 text-sm font-semibold text-indigo-900 hover:bg-indigo-100"
                            >
                                Synchroniser
                            </button>
                        @endif
                        <button
                            type="button"
                            id="actu-filters"
                            class="inline-flex items-center h-10 px-3 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-gray-900 hover:bg-slate-50"
                        >
                            Filtres
                        </button>
                    </div>
                </div>

                <div id="actu-chips" class="mt-3 -mx-6 px-6 pb-1 overflow-x-auto">
                    <div class="flex items-center gap-2 min-w-max">
                        @foreach ($chips as $c)
                            <button
                                type="button"
                                class="actu-chip h-9 px-3 rounded-full border text-sm font-semibold"
                                data-tag="{{ $c['key'] }}"
                            >
                                {{ $c['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="actu-error" class="hidden mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Erreur de chargement de l’actu
                </div>
            </div>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div id="actu-hero"></div>
            <div id="actu-list" class="space-y-3"></div>

            <div id="actu-more-wrap" class="pt-2">
                <button
                    type="button"
                    id="actu-more"
                    class="w-full h-11 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-gray-900 hover:bg-slate-50"
                >
                    Charger plus
                </button>
            </div>

            <div id="actu-more-skeleton" class="hidden space-y-3"></div>
        </div>
    </div>

    <script>
        (() => {
            const LIMIT = 20;

            const elHero = document.getElementById('actu-hero');
            const elList = document.getElementById('actu-list');
            const elError = document.getElementById('actu-error');
            const elMoreWrap = document.getElementById('actu-more-wrap');
            const elMoreBtn = document.getElementById('actu-more');
            const elMoreSkeleton = document.getElementById('actu-more-skeleton');
            const elRefresh = document.getElementById('actu-refresh');
            const elFilters = document.getElementById('actu-filters');
            const elChips = document.getElementById('actu-chips');
            const elLast = document.getElementById('actu-last');
            const elStale = document.getElementById('actu-stale');
            const elSync = document.getElementById('actu-sync');
            const isAdmin = @json(auth()->check() && auth()->user()?->can('manage-users') === true);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const chips = Array.from(document.querySelectorAll('.actu-chip'));

            let selectedTag = '';
            let nextCursor = null;
            let heroItem = null;
            let loading = false;

            const setError = (on) => {
                if (!elError) return;
                elError.classList.toggle('hidden', !on);
            };

            const escapeHtml = (s) => (s || '').replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const timeAgo = (iso) => {
                if (!iso) return '';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                const now = new Date();
                let diff = Math.floor((now.getTime() - d.getTime()) / 1000);

                const future = diff < 0;
                diff = Math.abs(diff);

                const min = Math.floor(diff / 60);
                const h = Math.floor(diff / 3600);
                const j = Math.floor(diff / 86400);

                let s;
                if (diff < 45) s = "à l’instant";
                else if (min < 60) s = `${min} min`;
                else if (h < 48) s = `${h} h`;
                else s = `${j} j`;

                if (s === "à l’instant") return s;
                return future ? `dans ${s}` : `il y a ${s}`;
            };

            const chipClasses = (active) => {
                return active
                    ? 'border-indigo-200 bg-indigo-50 text-indigo-800'
                    : 'border-slate-200 bg-white text-gray-900 hover:bg-slate-50';
            };

            const renderChips = () => {
                chips.forEach((b) => {
                    const tag = b.getAttribute('data-tag') || '';
                    b.className = `actu-chip h-9 px-3 rounded-full border text-sm font-semibold ${chipClasses(tag === selectedTag)}`;
                });
            };

            const skeletonLine = (w = 'w-2/3') => `<div class="h-3 ${w} rounded bg-slate-200"></div>`;

            const renderHeroSkeleton = () => {
                if (!elHero) return;
                elHero.innerHTML = `
                    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden animate-pulse">
                        <div class="aspect-[16/9] bg-slate-200"></div>
                        <div class="p-4 space-y-3">
                            <div class="h-5 w-28 rounded bg-slate-200"></div>
                            <div class="h-5 w-4/5 rounded bg-slate-200"></div>
                            ${skeletonLine('w-full')}
                            ${skeletonLine('w-5/6')}
                            <div class="h-3 w-40 rounded bg-slate-200"></div>
                        </div>
                    </div>
                `;
            };

            const renderListSkeleton = (count = 6) => {
                if (!elList) return;
                elList.innerHTML = Array.from({ length: count }).map(() => `
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 animate-pulse">
                        <div class="flex gap-3">
                            <div class="h-16 w-16 rounded-xl bg-slate-200 shrink-0"></div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="h-4 w-4/5 rounded bg-slate-200"></div>
                                <div class="h-4 w-3/5 rounded bg-slate-200"></div>
                                <div class="h-3 w-40 rounded bg-slate-200"></div>
                            </div>
                        </div>
                    </div>
                `).join('');
            };

            const renderMoreSkeleton = (count = 3) => {
                if (!elMoreSkeleton) return;
                elMoreSkeleton.classList.remove('hidden');
                elMoreSkeleton.innerHTML = Array.from({ length: count }).map(() => `
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 animate-pulse">
                        <div class="flex gap-3">
                            <div class="h-16 w-16 rounded-xl bg-slate-200 shrink-0"></div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="h-4 w-4/5 rounded bg-slate-200"></div>
                                <div class="h-4 w-3/5 rounded bg-slate-200"></div>
                                <div class="h-3 w-40 rounded bg-slate-200"></div>
                            </div>
                        </div>
                    </div>
                `).join('');
            };

            const hideMoreSkeleton = () => {
                if (!elMoreSkeleton) return;
                elMoreSkeleton.classList.add('hidden');
                elMoreSkeleton.innerHTML = '';
            };

            const renderHero = (item) => {
                if (!elHero) return;
                if (!item) {
                    elHero.innerHTML = '';
                    return;
                }

                const img = item.image_url
                    ? `<div class="aspect-[16/9] bg-slate-100 overflow-hidden">
                            <img src="${escapeHtml(item.image_url)}" alt="" class="w-full h-full object-cover" loading="lazy" />
                       </div>`
                    : '';

                const excerpt = item.excerpt ? escapeHtml(item.excerpt) : '';
                const meta = `${escapeHtml(item.source || '')}${item.published_at ? ` · ${escapeHtml(timeAgo(item.published_at))}` : ''}`;

                elHero.innerHTML = `
                    <a href="${escapeHtml(item.url || '#')}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-slate-200 bg-white overflow-hidden hover:bg-slate-50">
                        ${img}
                        <div class="p-4">
                            <div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border border-amber-200 bg-amber-50 text-amber-800">À LA UNE</div>
                            <div class="mt-2 text-lg font-bold text-gray-900 leading-snug">${escapeHtml(item.title || '')}</div>
                            ${excerpt ? `<div class="mt-2 text-sm text-slate-600 leading-relaxed">${excerpt}</div>` : ''}
                            <div class="mt-3 text-xs text-slate-500">${meta}</div>
                        </div>
                    </a>
                `;
            };

            const renderListItems = (items, append = false) => {
                if (!elList) return;

                const html = (items || []).map((it) => {
                    const img = it.image_url
                        ? `<img src="${escapeHtml(it.image_url)}" alt="" class="w-full h-full object-cover" loading="lazy" />`
                        : `<div class="w-full h-full bg-slate-100"></div>`;

                    const tag = it.tag ? `<span class="text-slate-400">·</span> <span class="text-slate-500">${escapeHtml(it.tag)}</span>` : '';
                    const meta = `${escapeHtml(it.source || '')}${it.published_at ? ` · ${escapeHtml(timeAgo(it.published_at))}` : ''} ${tag}`.trim();
                    const excerpt = it.excerpt ? escapeHtml(it.excerpt) : '';

                    return `
                        <a href="${escapeHtml(it.url || '#')}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-slate-200 bg-white p-3 hover:bg-slate-50">
                            <div class="flex gap-3">
                                <div class="h-16 w-16 rounded-xl overflow-hidden bg-slate-100 shrink-0">${img}</div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 leading-snug line-clamp-2">${escapeHtml(it.title || '')}</div>
                                    ${excerpt ? `<div class="mt-1 text-xs text-slate-600 line-clamp-1">${excerpt}</div>` : ''}
                                    <div class="mt-1.5 text-[11px] text-slate-500 flex items-center gap-2 flex-wrap">
                                        <span>${meta}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');

                if (append) elList.insertAdjacentHTML('beforeend', html);
                else elList.innerHTML = html;
            };

            const setMoreVisible = (visible) => {
                if (!elMoreWrap) return;
                elMoreWrap.classList.toggle('hidden', !visible);
            };

            const buildUrl = (cursor = null) => {
                const u = new URL('/api/news', window.location.origin);
                u.searchParams.set('limit', String(LIMIT));
                if (selectedTag) u.searchParams.set('tag', selectedTag);
                if (cursor) u.searchParams.set('cursor', cursor);
                return u.toString();
            };

            const fetchPage = async ({ reset = false } = {}) => {
                if (loading) return;
                loading = true;

                setError(false);

                if (reset) {
                    heroItem = null;
                    nextCursor = null;
                    renderHeroSkeleton();
                    renderListSkeleton();
                    setMoreVisible(false);
                } else {
                    setMoreVisible(false);
                    renderMoreSkeleton();
                }

                try {
                    const url = buildUrl(reset ? null : nextCursor);
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    if (!data || data.ok !== true || !Array.isArray(data.items)) throw new Error('Bad payload');

                    // Last update (fetched_at) hint
                    if (elLast) {
                        const lf = data.latest_fetched_at || null;
                        elLast.textContent = lf ? `Dernière synchro: ${timeAgo(lf)}` : '';
                        if (elStale) {
                            if (lf) {
                                const d = new Date(lf);
                                const ageHours = isNaN(d.getTime()) ? 0 : ((Date.now() - d.getTime()) / 3600000);
                                elStale.classList.toggle('hidden', !(ageHours >= 24));
                            } else {
                                elStale.classList.remove('hidden');
                            }
                        }
                    }

                    const items = data.items;
                    nextCursor = data.next_cursor ?? null;

                    if (reset) {
                        if (items.length > 0) {
                            heroItem = items[0];
                            renderHero(heroItem);
                            renderListItems(items.slice(1), false);
                        } else {
                            renderHero(null);
                            elList.innerHTML = `
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                                    Aucune actu pour l’instant.
                                </div>
                            `;
                        }
                    } else {
                        renderListItems(items, true);
                    }

                    hideMoreSkeleton();
                    setMoreVisible(!!nextCursor);
                } catch (e) {
                    hideMoreSkeleton();
                    setMoreVisible(false);
                    if (reset) {
                        renderHero(null);
                        elList.innerHTML = `
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                                Impossible de charger l’actu pour le moment.
                            </div>
                        `;
                    }
                    setError(true);
                } finally {
                    loading = false;
                    if (elMoreBtn) elMoreBtn.disabled = false;
                }
            };

            const setTag = (tag) => {
                selectedTag = tag || '';
                renderChips();
                fetchPage({ reset: true });
            };

            // Wiring
            renderChips();
            fetchPage({ reset: true });

            chips.forEach((b) => {
                b.addEventListener('click', () => setTag(b.getAttribute('data-tag') || ''));
            });

            if (elMoreBtn) {
                elMoreBtn.addEventListener('click', () => {
                    if (!nextCursor) return;
                    elMoreBtn.disabled = true;
                    fetchPage({ reset: false });
                });
            }

            if (elRefresh) {
                elRefresh.addEventListener('click', () => fetchPage({ reset: true }));
            }

            if (elSync && isAdmin) {
                elSync.addEventListener('click', async () => {
                    if (loading) return;
                    loading = true;
                    setError(false);
                    elSync.disabled = true;

                    try {
                        const resp = await fetch('/api/news/import', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                            },
                            credentials: 'same-origin',
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || data?.ok !== true) {
                            throw new Error(data?.output || `Import failed (HTTP ${resp.status})`);
                        }
                        await fetchPage({ reset: true });
                    } catch (e) {
                        setError(true);
                    } finally {
                        loading = false;
                        elSync.disabled = false;
                    }
                });
            }

            if (elFilters && elChips) {
                elFilters.addEventListener('click', () => {
                    elChips.classList.toggle('hidden');
                });
            }
        })();
    </script>
</x-app-layout>
