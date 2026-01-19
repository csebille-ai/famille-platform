@php
    /**
     * Expects:
     * - $cards: array
     * - $spread: string ('three'|'five'|...)
     * - $idPrefix: string
     */
    $cards = (array) ($cards ?? []);
    $spread = (string) ($spread ?? 'three');
    $idPrefix = (string) ($idPrefix ?? 'tarot');

    $N = count($cards);
    $supportsRituel = in_array($N, [3, 5], true);

    $normalizeKeywords = function ($raw): array {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $s = trim((string) $raw);
            if ($s === '') return [];
            $items = preg_split('/\s*(?:,|;|\||•)\s*/u', $s) ?: [];
        }
        $items = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
        return array_slice($items, 0, 3);
    };
@endphp

@if($N > 0)
    <div id="{{ $idPrefix }}-cards-ui" class="space-y-4" data-tarot-cards-ui data-count="{{ $N }}" data-supports-rituel="{{ $supportsRituel ? '1' : '0' }}">
        <div class="flex items-center justify-between gap-3">
            <div class="text-sm font-semibold text-slate-700">Cartes</div>

            <div class="inline-flex rounded-xl border border-black/10 bg-white p-1" role="tablist" aria-label="Vue">
                <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-semibold rounded-lg text-slate-700 hover:bg-slate-50"
                    data-view-btn="focus"
                >
                    Focus
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-semibold rounded-lg text-slate-700 hover:bg-slate-50"
                    data-view-btn="rituel"
                    {{ $supportsRituel ? '' : 'disabled' }}
                    aria-disabled="{{ $supportsRituel ? 'false' : 'true' }}"
                >
                    Rituel
                </button>
            </div>
        </div>

        {{-- Focus view --}}
        <div data-view="focus" class="space-y-3">
            <div class="fam-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-slate-600">Carte <span data-active-pos>1</span>/<span data-total>{{ $N }}</span></div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-black/10 bg-white hover:bg-slate-50" data-nav="prev" aria-label="Carte précédente">
                            <i class="ph ph-caret-left" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-black/10 bg-white hover:bg-slate-50" data-nav="next" aria-label="Carte suivante">
                            <i class="ph ph-caret-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-3">
                    <div
                        class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth px-2 py-2"
                        style="-webkit-overflow-scrolling: touch"
                        data-carousel
                        aria-label="Carrousel"
                    >
                        @foreach($cards as $i => $c)
                            @php
                                $file = (string) ($c['file'] ?? '');
                                $name = (string) ($c['name'] ?? '');
                                $reversed = !empty($c['reversed']);
                                $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';
                                $kws = $normalizeKeywords($c['keywords'] ?? '');
                            @endphp
                            <button
                                type="button"
                                class="snap-center shrink-0 w-[72%] max-w-[18rem] transition-[transform,opacity] duration-200 ease-out scale-[0.92] opacity-90"
                                data-card-slide
                                data-index="{{ (int) $i }}"
                                data-name="{{ e($name) }}"
                                data-img="{{ e($img) }}"
                                data-reversed="{{ $reversed ? '1' : '0' }}"
                                data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
                                aria-label="Carte {{ (int) $i + 1 }}"
                            >
                                <div class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
                                    <div class="w-full aspect-[2/3] bg-white">
                                        @if($img !== '')
                                            <img
                                                src="{{ $img }}"
                                                alt="{{ e($name) }}"
                                                class="h-full w-full object-contain bg-white"
                                                style="transform: {{ $reversed ? 'rotate(180deg)' : 'none' }};"
                                                loading="lazy"
                                                decoding="async"
                                            />
                                        @endif
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-3 text-center">
                        <div class="text-sm font-semibold text-slate-900" data-active-name></div>
                        <div class="mt-2 flex items-center justify-center gap-2 flex-wrap" data-active-kws></div>
                        <div class="mt-3 text-xs text-slate-500">Swipe pour naviguer • Tap carte active = zoom</div>
                    </div>
                </div>
            </div>

            @if($N > 1)
                <div class="fam-card p-3">
                    <div class="flex items-center gap-2 overflow-x-auto" style="-webkit-overflow-scrolling: touch">
                        @foreach($cards as $i => $c)
                            @php
                                $file = (string) ($c['file'] ?? '');
                                $name = (string) ($c['name'] ?? '');
                                $reversed = !empty($c['reversed']);
                                $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';
                                $kws = $normalizeKeywords($c['keywords'] ?? '');
                            @endphp
                            <button
                                type="button"
                                class="shrink-0 rounded-xl border border-black/10 bg-white p-1.5 hover:bg-slate-50"
                                data-card-thumb
                                data-index="{{ (int) $i }}"
                                data-name="{{ e($name) }}"
                                data-img="{{ e($img) }}"
                                data-reversed="{{ $reversed ? '1' : '0' }}"
                                data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
                                aria-label="Ouvrir la carte {{ (int) $i + 1 }}"
                            >
                                <div class="w-16 aspect-[2/3] overflow-hidden rounded-lg bg-white">
                                    @if($img !== '')
                                        <img
                                            src="{{ $img }}"
                                            alt=""
                                            class="h-full w-full object-contain bg-white"
                                            style="transform: {{ $reversed ? 'rotate(180deg)' : 'none' }};"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Rituel view --}}
        <div data-view="rituel" class="space-y-3" {{ $supportsRituel ? '' : 'hidden' }}>
            <div class="fam-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-slate-600">Vue rituel</div>
                    <div class="text-xs font-semibold text-slate-600">Carte <span data-active-pos>1</span>/<span data-total>{{ $N }}</span></div>
                </div>

                <div class="mt-3 rounded-2xl border border-black/10 bg-white/70 shadow-sm">
                    <div class="w-full" style="height: clamp(240px, 64vw, 280px);">
                        <div class="relative h-full w-full overflow-hidden" data-spread>
                            <div class="absolute inset-0 hidden pointer-events-none" data-rituel-debug>
                                <div class="absolute left-4 top-4 h-24 w-16 rounded-xl bg-rose-300/80 ring-2 ring-rose-500/60" style="z-index: 1"></div>
                                <div class="absolute left-24 top-10 h-24 w-16 rounded-xl bg-emerald-300/80 ring-2 ring-emerald-500/60" style="z-index: 2"></div>
                                <div class="absolute left-44 top-16 h-24 w-16 rounded-xl bg-sky-300/80 ring-2 ring-sky-500/60" style="z-index: 3"></div>
                            </div>

                            @foreach($cards as $i => $c)
                            @php
                                $file = (string) ($c['file'] ?? '');
                                $name = (string) ($c['name'] ?? '');
                                $reversed = !empty($c['reversed']);
                                $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';
                                $kws = $normalizeKeywords($c['keywords'] ?? '');
                            @endphp
                            <button
                                type="button"
                                class="absolute left-1/2 top-[90%] origin-bottom rounded-2xl border border-black/10 bg-white shadow-sm transition-[transform,filter,box-shadow] duration-200 ease-out"
                                style="width: clamp(120px, 34vw, 140px); height: clamp(180px, 51vw, 210px);"
                                data-card-fan
                                data-index="{{ (int) $i }}"
                                data-name="{{ e($name) }}"
                                data-img="{{ e($img) }}"
                                data-reversed="{{ $reversed ? '1' : '0' }}"
                                data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
                                aria-label="Choisir la carte {{ (int) $i + 1 }}"
                            >
                                <div class="h-full w-full overflow-hidden rounded-2xl bg-white">
                                    @if($img !== '')
                                        <img
                                            src="{{ $img }}"
                                            alt=""
                                            class="h-full w-full object-contain bg-white"
                                            style="transform: {{ $reversed ? 'rotate(180deg)' : 'none' }};"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4 fam-card-soft p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 truncate" data-active-name></div>
                            <div class="mt-2 flex items-center gap-2 flex-wrap" data-active-kws></div>
                        </div>
                        <div class="text-xs font-semibold text-slate-500 shrink-0">Carte <span data-active-pos>1</span>/<span data-total>{{ $N }}</span></div>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">Tap carte = focus • Tap carte active = zoom</div>
                </div>
            </div>
        </div>

        {{-- Zoom modal --}}
        <div class="fixed inset-0 z-[80] hidden" data-zoom-modal aria-hidden="true">
            <div class="absolute inset-0 bg-black/70" data-zoom-close></div>
            <div class="absolute inset-0 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="relative w-full max-w-md">
                    <button type="button" class="absolute -top-3 -right-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white shadow" data-zoom-close aria-label="Fermer">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                    <div class="rounded-2xl bg-white overflow-hidden shadow-lg">
                        <div class="w-full aspect-[2/3] bg-white" style="touch-action: pinch-zoom;">
                            <img data-zoom-img src="" alt="" class="h-full w-full object-contain bg-white" />
                        </div>
                    </div>
                    <div class="mt-3 text-center text-xs text-white/80">Pincer pour zoomer • Tap pour fermer</div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const root = document.getElementById(@json($idPrefix . '-cards-ui'));
        if (!root) return;

        const STORAGE_KEY = 'tarot.viewMode';
        const supportsRituel = root.getAttribute('data-supports-rituel') === '1';
        const count = Number(root.getAttribute('data-count') || '0');
        const DEBUG_RITUEL = (() => {
            try {
                return new URLSearchParams(window.location.search).has('debugRituel');
            } catch (e) {
                return false;
            }
        })();

        const canUseRituelNow = () => {
            if (!supportsRituel) return false;
            if (!(count === 3 || count === 5)) return false;
            const isLandscape = window.matchMedia && window.matchMedia('(orientation: landscape)').matches;
            const narrow = window.matchMedia && window.matchMedia('(max-width: 390px)').matches;
            return !isLandscape && !narrow;
        };

        let viewMode = 'focus';
        try {
            const v = (localStorage.getItem(STORAGE_KEY) || '').toLowerCase();
            if (v === 'rituel' || v === 'focus') viewMode = v;
        } catch (e) {}

        const setViewMode = (mode) => {
            const allowed = (mode === 'rituel' && canUseRituelNow()) ? 'rituel' : 'focus';
            viewMode = allowed;
            root.querySelectorAll('[data-view]')?.forEach((el) => {
                el.hidden = el.getAttribute('data-view') !== allowed;
            });
            root.querySelectorAll('[data-view-btn]')?.forEach((btn) => {
                const m = btn.getAttribute('data-view-btn');
                const isActive = m === allowed;
                btn.classList.toggle('bg-[color:rgba(14,165,160,0.10)]', isActive);
                btn.classList.toggle('text-[color:var(--fam-primary-hover)]', isActive);
                btn.classList.toggle('border', isActive);
                btn.classList.toggle('border-[color:rgba(14,165,160,0.14)]', isActive);

                if (m === 'rituel') {
                    const enabled = canUseRituelNow();
                    btn.disabled = !enabled;
                    btn.classList.toggle('opacity-40', !enabled);
                    btn.classList.toggle('cursor-not-allowed', !enabled);
                }
            });
            try { localStorage.setItem(STORAGE_KEY, allowed); } catch (e) {}

            if (allowed === 'rituel') {
                // Run layout after the view becomes visible so getBoundingClientRect() is non-zero.
                requestAnimationFrame(() => layoutFan());
            }
        };

        // Active index shared.
        let activeIndex = 0;
        let syncingFromScroll = false;

        const updateActiveLabels = () => {
            root.querySelectorAll('[data-active-pos]').forEach((el) => {
                el.textContent = String(activeIndex + 1);
            });
        };

        const renderKeywords = (container, kws) => {
            if (!container) return;
            container.innerHTML = '';
            (kws || []).slice(0, 3).forEach((k) => {
                const chip = document.createElement('span');
                chip.className = 'fam-chip text-xs';
                chip.textContent = k;
                container.appendChild(chip);
            });
        };

        const getCardBtnByIndex = (selector, idx) => root.querySelector(`${selector}[data-index="${idx}"]`);

        const syncActiveCardPanels = () => {
            const anyBtn = getCardBtnByIndex('[data-card-slide]', activeIndex)
                || getCardBtnByIndex('[data-card-thumb]', activeIndex)
                || getCardBtnByIndex('[data-card-fan]', activeIndex);
            if (!anyBtn) return;

            const name = anyBtn.getAttribute('data-name') || '';
            const img = anyBtn.getAttribute('data-img') || '';
            const reversed = anyBtn.getAttribute('data-reversed') === '1';
            let kws = [];
            try {
                kws = JSON.parse(anyBtn.getAttribute('data-kws') || '[]');
            } catch (e) {}

            root.querySelectorAll('[data-active-name]').forEach((el) => { el.textContent = name; });
            root.querySelectorAll('[data-active-kws]').forEach((el) => renderKeywords(el, kws));
            updateActiveLabels();

            root.querySelectorAll('[data-card-thumb]').forEach((b) => {
                const idx = Number(b.getAttribute('data-index') || '0');
                b.classList.toggle('ring-2', idx === activeIndex);
                b.classList.toggle('ring-[color:rgba(14,165,160,0.45)]', idx === activeIndex);
            });
        };

        const setActiveIndex = (idx) => {
            if (!Number.isFinite(idx)) return;
            const next = Math.max(0, Math.min(count - 1, idx));
            activeIndex = next;
            syncActiveCardPanels();
            layoutFan();
            if (viewMode === 'focus') {
                scrollToSlide(activeIndex);
            }
        };

        // Modal
        const modal = root.querySelector('[data-zoom-modal]');
        const modalImg = root.querySelector('[data-zoom-img]');
        const closeModal = () => {
            if (!modal) return;
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            if (modalImg) modalImg.src = '';
        };
        const openModal = (btn) => {
            if (!modal || !modalImg || !btn) return;
            const img = btn.getAttribute('data-img') || '';
            const name = btn.getAttribute('data-name') || '';
            const reversed = btn.getAttribute('data-reversed') === '1';
            modalImg.src = img;
            modalImg.alt = name;
            modalImg.style.transform = reversed ? 'rotate(180deg)' : 'none';
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        };

        root.querySelectorAll('[data-zoom-close]').forEach((el) => el.addEventListener('click', closeModal));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

        // Focus carousel
        const carousel = root.querySelector('[data-carousel]');
        const slides = Array.from(root.querySelectorAll('[data-card-slide]'));

        const updateSlideStyles = () => {
            slides.forEach((b) => {
                const idx = Number(b.getAttribute('data-index') || '0');
                const isActive = idx === activeIndex;
                b.classList.toggle('scale-100', isActive);
                b.classList.toggle('opacity-100', isActive);
                b.classList.toggle('scale-[0.92]', !isActive);
                b.classList.toggle('opacity-90', !isActive);
            });
        };

        const scrollToSlide = (idx, behavior = 'smooth') => {
            if (!carousel || !slides[idx]) return;
            try {
                syncingFromScroll = true;
                slides[idx].scrollIntoView({ behavior, block: 'nearest', inline: 'center' });
            } catch (e) {
                // ignore
            } finally {
                setTimeout(() => { syncingFromScroll = false; }, 180);
            }
            updateSlideStyles();
        };

        const pickNearestSlide = () => {
            if (!carousel || slides.length === 0) return;
            const center = carousel.scrollLeft + (carousel.clientWidth / 2);
            let bestIdx = 0;
            let bestDist = Infinity;
            for (const b of slides) {
                const idx = Number(b.getAttribute('data-index') || '0');
                const mid = b.offsetLeft + (b.clientWidth / 2);
                const d = Math.abs(mid - center);
                if (d < bestDist) {
                    bestDist = d;
                    bestIdx = idx;
                }
            }
            if (bestIdx !== activeIndex) {
                activeIndex = bestIdx;
                syncActiveCardPanels();
                layoutFan();
            }
            updateSlideStyles();
        };

        let scrollTimer = null;
        if (carousel) {
            carousel.addEventListener('scroll', () => {
                if (syncingFromScroll) return;
                if (scrollTimer) window.clearTimeout(scrollTimer);
                scrollTimer = window.setTimeout(pickNearestSlide, 80);
            }, { passive: true });
        }

        slides.forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                if (idx === activeIndex) {
                    openModal(btn);
                } else {
                    activeIndex = idx;
                    syncActiveCardPanels();
                    updateSlideStyles();
                    scrollToSlide(idx);
                }
            });
        });

        // Hook up buttons
        root.querySelectorAll('[data-view-btn]').forEach((btn) => {
            btn.addEventListener('click', () => setViewMode(btn.getAttribute('data-view-btn') || 'focus'));
        });

        root.querySelectorAll('[data-nav]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const dir = btn.getAttribute('data-nav');
                setActiveIndex(activeIndex + (dir === 'next' ? 1 : -1));
            });
        });

        root.querySelectorAll('[data-card-thumb]').forEach((btn) => {
            btn.addEventListener('click', () => setActiveIndex(Number(btn.getAttribute('data-index') || '0')));
        });

        root.querySelectorAll('[data-card-fan]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                if (idx === activeIndex) {
                    openModal(btn);
                } else {
                    setActiveIndex(idx);
                }
            });
        });

        // (Zoom is handled by tapping the active slide/fan card)

        // Fan layout
        const spread = root.querySelector('[data-spread]');
        const fanButtons = Array.from(root.querySelectorAll('[data-card-fan]'));
        const debugLayer = root.querySelector('[data-rituel-debug]');

        if (DEBUG_RITUEL && spread) {
            spread.style.overflow = 'visible';
            spread.style.backgroundImage = 'linear-gradient(0deg, rgba(2,6,23,0.06), rgba(2,6,23,0.06))';
        }
        if (DEBUG_RITUEL && debugLayer) {
            debugLayer.classList.remove('hidden');
        }

        const baseLayout = (n) => {
            if (n === 3) {
                return {
                    stepAngle: 13,
                    stepX: 18,
                    stepY: 10,
                    scaleDrop: 0.04,
                };
            }
            return {
                stepAngle: 9,
                stepX: 18,
                stepY: 10,
                scaleDrop: 0.04,
            };
        };

        const layoutFan = () => {
            if (!spread || fanButtons.length === 0) return;
            if (!canUseRituelNow()) return;

            // Ensure the active card is painted last (on top) in addition to z-index.
            const activeBtn = root.querySelector(`[data-card-fan][data-index="${activeIndex}"]`);
            if (activeBtn && activeBtn.parentElement === spread) {
                spread.appendChild(activeBtn);
            }

            const rect = spread.getBoundingClientRect();
            const pad = 16;
            const baselineY = rect.height * 0.90;
            const minTop = rect.height * 0.10;

            // Use computed size from the first card (width/height are explicitly set in markup).
            const cardRect = fanButtons[0].getBoundingClientRect();
            const cardW = cardRect.width || 120;
            const cardH = cardRect.height || 180;
            const n = fanButtons.length;
            const base = baseLayout(n);
            const tMax = (n - 1) / 2;
            const maxX = Math.max(0, (rect.width / 2) - (cardW / 2) - pad);
            const maxAbsX = Math.abs(tMax * base.stepX) || 1;
            const k = Math.min(1, maxX / maxAbsX);

            const center = (n - 1) / 2;

            fanButtons.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const t = idx - center;
                const angle = (t * base.stepAngle) * k;
                const rawX = (t * base.stepX) * k;
                // Keep the bottom of cards near the baseline: outer cards rise slightly instead of going further down.
                const rawY = -(Math.abs(t) * base.stepY) * k;
                const isActive = idx === activeIndex;

                // Clamp X so cards stay within the frame.
                const x = Math.max(-maxX, Math.min(maxX, rawX));

                // Active lift (small): move up a bit.
                const activeLift = isActive ? -8 : 0;

                // Clamp Y so the card top doesn't go above minTop.
                let y = rawY + activeLift;
                const topAfter = baselineY - cardH + y;
                if (topAfter < minTop) {
                    y += (minTop - topAfter);
                }
                // Also prevent the bottom from going below the scene.
                const bottomAfter = baselineY + y;
                const maxBottom = rect.height - pad;
                if (bottomAfter > maxBottom) {
                    y -= (bottomAfter - maxBottom);
                }

                const scaleBase = 1 - (Math.abs(t) * base.scaleDrop);
                const scale = isActive ? (scaleBase + 0.07) : scaleBase;
                const shadow = isActive ? '0 10px 28px rgba(15,23,42,0.16)' : '0 6px 16px rgba(15,23,42,0.10)';
                btn.style.zIndex = String(isActive ? 20 : 10 + idx);
                btn.style.boxShadow = shadow;
                btn.style.filter = isActive ? 'none' : 'saturate(0.92) contrast(0.98)';
                btn.style.transformOrigin = '50% 100%';
                btn.style.top = `${baselineY}px`;
                btn.style.transform = `translate(-50%, -100%) translate(${x}px, ${y}px) rotate(${angle}deg) scale(${scale})`;

                if (DEBUG_RITUEL) {
                    btn.style.outline = isActive ? '2px solid rgba(14,165,160,0.55)' : '1px dashed rgba(2,6,23,0.28)';
                    btn.style.outlineOffset = '2px';
                }
            });
        };

        // Simple swipe fallback
        if (spread) {
            let startX = null;
            let startY = null;
            spread.addEventListener('pointerdown', (e) => {
                startX = e.clientX;
                startY = e.clientY;
            });
            spread.addEventListener('pointerup', (e) => {
                if (startX == null || startY == null) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                startX = null;
                startY = null;
                if (Math.abs(dx) < 30 || Math.abs(dx) < Math.abs(dy)) return;
                setActiveIndex(activeIndex + (dx < 0 ? 1 : -1));
            });
        }

        const ro = (window.ResizeObserver && spread) ? new ResizeObserver(() => {
            if (viewMode === 'rituel') layoutFan();
        }) : null;
        if (ro && spread) ro.observe(spread);

        window.addEventListener('resize', () => {
            // If rituel became unsupported, force focus.
            if (viewMode === 'rituel' && !canUseRituelNow()) {
                setViewMode('focus');
            }
            if (viewMode === 'rituel') layoutFan();
        });

        // Init
        setViewMode(viewMode);
        setActiveIndex(0);
        updateSlideStyles();
        // Ensure the first slide is centered on load.
        scrollToSlide(0, 'auto');
    })();
    </script>
@endif
