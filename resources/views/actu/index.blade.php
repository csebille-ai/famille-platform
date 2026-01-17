@php
    $bucketChips = [
        ['key' => 'all', 'label' => 'Tout'],
        ['key' => 'infos', 'label' => 'Infos'],
        ['key' => 'sorties', 'label' => 'Sorties'],
        ['key' => 'sport', 'label' => 'Sport'],
    ];
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-3xl mx-auto">
        <div id="actu-sticky" class="sticky z-[55] bg-[#F6F2EC]/95 supports-[backdrop-filter]:bg-[#F6F2EC]/85 supports-[backdrop-filter]:backdrop-blur-xl border-b border-black/10" style="top: 0px">
            <div class="px-6 pt-4 pb-3">
                <div id="actu-buckets" class="mt-3 -mx-6 px-6 pb-1 overflow-x-auto">
                    <div class="flex items-center gap-2 min-w-max">
                        @foreach ($bucketChips as $b)
                            <button
                                type="button"
                                class="actu-bucket h-9 px-3 rounded-full border text-sm font-bold"
                                data-bucket="{{ $b['key'] }}"
                            >
                                {{ $b['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="actu-new" class="hidden mt-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold">Nouvelles actus disponibles</div>
                        <button
                            type="button"
                            id="actu-new-btn"
                            class="shrink-0 inline-flex items-center h-9 px-3 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700"
                        >
                            Afficher
                        </button>
                    </div>
                </div>

                <div id="actu-error" class="hidden mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Erreur de chargement de l’actu
                </div>
            </div>
        </div>

        <script>
            (() => {
                const sticky = document.getElementById('actu-sticky');
                if (!sticky) return;

                const nav = document.querySelector('nav');
                if (!nav) return;

                const applyTop = () => {
                    const h = Math.ceil(nav.offsetHeight || nav.getBoundingClientRect().height || 0);
                    sticky.style.top = `${h}px`;
                };

                const scheduleApply = () => {
                    requestAnimationFrame(() => requestAnimationFrame(applyTop));
                };

                scheduleApply();
                window.addEventListener('load', scheduleApply, { passive: true });
                window.addEventListener('resize', scheduleApply, { passive: true });

                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', scheduleApply, { passive: true });
                    window.visualViewport.addEventListener('scroll', scheduleApply, { passive: true });
                }

                if (window.ResizeObserver) {
                    const ro = new ResizeObserver(scheduleApply);
                    ro.observe(nav);
                }
            })();
        </script>

        <div class="px-6 py-5 space-y-4">
            <div id="actu-hero"></div>
            <div id="actu-list" class="space-y-3"></div>

            <div id="actu-more-wrap" class="pt-2">
                <button
                    type="button"
                    id="actu-more"
                    class="w-full h-11 rounded-xl border border-black/10 bg-white text-sm font-semibold text-gray-900 transition-colors hover:bg-[rgba(14,165,160,0.10)] active:bg-[rgba(14,165,160,0.16)]"
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
            const elNew = document.getElementById('actu-new');
            const elNewBtn = document.getElementById('actu-new-btn');
            const bucketButtons = Array.from(document.querySelectorAll('.actu-bucket'));

            let selectedBucket = 'all';
            let nextCursor = null;
            let heroItem = null;
            let loading = false;
            let pendingFirstPage = null;
            let autoTimer = null;

            const seenUrls = new Set();

            const AUTO_REFRESH_MS = 90_000;
            const AT_TOP_PX = 140;

            const setError = (on) => {
                if (!elError) return;
                elError.classList.toggle('hidden', !on);
            };

            const escapeHtml = (s) => (s || '').replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const cleanSource = (s) => (s || '').replace(/\s*\(rss\)\s*$/i, '').trim();

            const loadBucketPref = () => {
                try {
                    const v = window.localStorage.getItem('actu.bucket');
                    if (v === 'all' || v === 'infos' || v === 'sorties' || v === 'sport') return v;
                } catch (e) {}
                return 'all';
            };

            const saveBucketPref = (v) => {
                try { window.localStorage.setItem('actu.bucket', v); } catch (e) {}
            };

            const timeAgo = (iso) => {
                if (!iso) return '';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                const now = new Date();
                let diff = Math.floor((now.getTime() - d.getTime()) / 1000);
                // Clamp clock skew / future timestamps to “just now”.
                diff = Math.max(0, diff);

                const min = Math.floor(diff / 60);
                const h = Math.floor(diff / 3600);
                const j = Math.floor(diff / 86400);

                let s;
                if (diff < 45) s = "à l’instant";
                else if (min < 60) s = `${min} min`;
                else if (h < 48) s = `${h} h`;
                else s = `${j} j`;

                if (s === "à l’instant") return s;
                return `depuis ${s}`;
            };

            const itemKey = (it) => {
                if (!it) return '';
                return `${it.published_at || ''}|${it.url || ''}|${it.title || ''}`;
            };

            const canonicalUrlKey = (rawUrl) => {
                const s = String(rawUrl || '').trim();
                if (!s) return '';

                try {
                    const u = new URL(s, window.location.origin);

                    // Strip fragments.
                    u.hash = '';

                    // Remove common tracking params.
                    const toDelete = [];
                    u.searchParams.forEach((_, k) => {
                        const key = String(k || '').toLowerCase();
                        if (key.startsWith('utm_')) toDelete.push(k);
                        else if (['xtor', 'fbclid', 'gclid', 'mc_cid', 'mc_eid'].includes(key)) toDelete.push(k);
                    });
                    toDelete.forEach((k) => u.searchParams.delete(k));

                    // Normalize path.
                    let path = u.pathname || '/';
                    path = path.replace(/\/+/g, '/');

                    // Special case: some agenda systems duplicate pages with -N suffix.
                    // Example: /agenda/foo-5/ => /agenda/foo/
                    if (path.toLowerCase().includes('/agenda/')) {
                        const parts = path.split('/').filter(Boolean);
                        if (parts.length > 0) {
                            const last = parts[parts.length - 1];
                            const m = last.match(/^(.*?)-(\d+)$/);
                            if (m && m[1]) {
                                parts[parts.length - 1] = m[1];
                                path = '/' + parts.join('/') + '/';
                            }
                        }
                    }

                    // Trim trailing slash (except root).
                    if (path.length > 1) path = path.replace(/\/+$/, '');

                    const origin = (u.origin || '').toLowerCase();
                    const qs = u.searchParams.toString();
                    return origin + path + (qs ? `?${qs}` : '');
                } catch {
                    // Fallback: best-effort normalization.
                    return s.replace(/#.*$/, '').replace(/[\?&](utm_[^=&]+|fbclid|gclid|xtor|mc_cid|mc_eid)=[^&]*/gi, '').trim();
                }
            };

            const isDuplicate = (it) => {
                const key = canonicalUrlKey(it?.url);
                if (!key) return false;
                return seenUrls.has(key);
            };

            const markSeen = (it) => {
                const key = canonicalUrlKey(it?.url);
                if (!key) return;
                seenUrls.add(key);
            };

            const dedupeItems = (items) => {
                const out = [];
                (items || []).forEach((it) => {
                    if (!it || !it.url) return;
                    if (isDuplicate(it)) return;
                    markSeen(it);
                    out.push(it);
                });
                return out;
            };

            const bucketClasses = (active) => {
                return active
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-black/10 bg-white text-gray-900 hover:bg-[rgba(14,165,160,0.10)] active:bg-[rgba(14,165,160,0.16)]';
            };

            const renderBuckets = () => {
                bucketButtons.forEach((b) => {
                    const bucket = b.getAttribute('data-bucket') || '';
                    b.className = `actu-bucket h-9 px-3 rounded-full border text-sm font-bold ${bucketClasses(bucket === selectedBucket)}`;
                });
            };

            const skeletonLine = (w = 'w-2/3') => `<div class="h-3 ${w} rounded bg-slate-200"></div>`;

            const renderHeroSkeleton = () => {
                if (!elHero) return;
                elHero.innerHTML = `
                    <div class="rounded-2xl border border-black/10 bg-white overflow-hidden animate-pulse">
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
                const parts = [escapeHtml(cleanSource(item.source || ''))];
                if (item.published_at) parts.push(escapeHtml(timeAgo(item.published_at)));
                if (item.tag) parts.push(escapeHtml(item.tag));
                if (item.sub_category) parts.push(escapeHtml(item.sub_category));
                const meta = parts.filter(Boolean).join(' · ');

                elHero.innerHTML = `
                    <a href="${escapeHtml(item.url || '#')}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-black/10 bg-white overflow-hidden hover:bg-[rgba(14,165,160,0.10)] active:bg-[rgba(14,165,160,0.16)]">
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

                    const metaParts = [escapeHtml(cleanSource(it.source || ''))];
                    if (it.published_at) metaParts.push(escapeHtml(timeAgo(it.published_at)));
                    if (it.tag) metaParts.push(escapeHtml(it.tag));
                    if (it.sub_category) metaParts.push(escapeHtml(it.sub_category));
                    const meta = metaParts.filter(Boolean).join(' · ');
                    const excerpt = it.excerpt ? escapeHtml(it.excerpt) : '';

                    return `
                        <a href="${escapeHtml(it.url || '#')}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-black/10 bg-white p-3 hover:bg-[rgba(14,165,160,0.10)] active:bg-[rgba(14,165,160,0.16)]">
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
                if (selectedBucket && selectedBucket !== 'all') u.searchParams.set('bucket', selectedBucket);
                if (cursor) u.searchParams.set('cursor', cursor);
                return u.toString();
            };

            const fetchFirstPageData = async () => {
                const url = buildUrl(null);
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const data = await resp.json();
                if (!data || data.ok !== true || !Array.isArray(data.items)) throw new Error('Bad payload');
                return data;
            };

            const applyResetData = (data) => {
                seenUrls.clear();

                const items = dedupeItems(data.items || []);
                nextCursor = data.next_cursor ?? null;

                if (items.length > 0) {
                    heroItem = items[0];
                    renderHero(heroItem);
                    renderListItems(items.slice(1), false);
                } else {
                    heroItem = null;
                    renderHero(null);
                    elList.innerHTML = `
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                            Aucune actu pour l’instant.
                        </div>
                    `;
                }

                hideMoreSkeleton();
                setMoreVisible(!!nextCursor);
            };

            const setNewBannerVisible = (visible) => {
                if (!elNew) return;
                elNew.classList.toggle('hidden', !visible);
            };

            const fetchPage = async ({ reset = false } = {}) => {
                if (loading) return;
                loading = true;

                setError(false);

                if (reset) {
                    heroItem = null;
                    nextCursor = null;
                    seenUrls.clear();
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

                    const items = reset ? dedupeItems(data.items) : dedupeItems(data.items);
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
                    setNewBannerVisible(false);
                    pendingFirstPage = null;
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

            const setBucket = (bucket) => {
                selectedBucket = (bucket === 'all' || bucket === 'infos' || bucket === 'sorties' || bucket === 'sport') ? bucket : 'all';
                saveBucketPref(selectedBucket);
                renderBuckets();
                setNewBannerVisible(false);
                pendingFirstPage = null;
                fetchPage({ reset: true });
            };

            const scheduleAutoRefresh = () => {
                if (autoTimer) {
                    clearInterval(autoTimer);
                    autoTimer = null;
                }
                autoTimer = setInterval(async () => {
                    if (document.visibilityState !== 'visible') return;
                    if (loading) return;

                    loading = true;
                    try {
                        const data = await fetchFirstPageData();
                        const newTop = data.items?.[0] ?? null;
                        const currentTop = heroItem;
                        const changed = itemKey(newTop) !== '' && itemKey(newTop) !== itemKey(currentTop);
                        if (!changed) return;

                        if (window.scrollY <= AT_TOP_PX) {
                            applyResetData(data);
                            setNewBannerVisible(false);
                            pendingFirstPage = null;
                        } else {
                            pendingFirstPage = data;
                            setNewBannerVisible(true);
                        }
                    } catch (e) {
                        // ignore background refresh errors
                    } finally {
                        loading = false;
                    }
                }, AUTO_REFRESH_MS);
            };

            // Wiring
            selectedBucket = loadBucketPref();
            renderBuckets();
            fetchPage({ reset: true });
            scheduleAutoRefresh();

            bucketButtons.forEach((b) => {
                b.addEventListener('click', () => setBucket(b.getAttribute('data-bucket') || 'infos'));
            });

            if (elMoreBtn) {
                elMoreBtn.addEventListener('click', () => {
                    if (!nextCursor) return;
                    elMoreBtn.disabled = true;
                    fetchPage({ reset: false });
                });
            }

            if (elNewBtn) {
                elNewBtn.addEventListener('click', () => {
                    if (pendingFirstPage) {
                        applyResetData(pendingFirstPage);
                        pendingFirstPage = null;
                        setNewBannerVisible(false);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    fetchPage({ reset: true });
                });
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    // quick catch-up when user comes back
                    fetchPage({ reset: true });
                }
            });

        })();
    </script>
</x-app-layout>
