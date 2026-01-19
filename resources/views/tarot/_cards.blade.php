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

                <div class="mt-3 flex justify-center">
                    <button type="button" class="group relative" data-zoom-active aria-label="Zoom">
                        <div class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
                            <div class="w-[min(70vw,18rem)] aspect-[2/3] bg-white">
                                <img
                                    data-active-img
                                    src=""
                                    alt=""
                                    class="h-full w-full object-contain bg-white"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <div class="text-sm font-semibold text-slate-900" data-active-name></div>
                            <div class="mt-2 flex items-center justify-center gap-2 flex-wrap" data-active-kws></div>
                        </div>
                    </button>
                </div>

                <div class="mt-4 flex items-center justify-center">
                    <div class="text-xs text-slate-500">Tap carte = zoom</div>
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

                <div class="mt-3 rounded-2xl border border-black/10 bg-white/70 shadow-sm overflow-hidden">
                    <div
                        class="relative w-full overflow-hidden"
                        data-spread
                        style="height: min(54vw, 18rem);"
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
                                class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-black/10 bg-white shadow-sm transition-[transform,filter,box-shadow] duration-200 ease-out"
                                data-card-fan
                                data-index="{{ (int) $i }}"
                                data-name="{{ e($name) }}"
                                data-img="{{ e($img) }}"
                                data-reversed="{{ $reversed ? '1' : '0' }}"
                                data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
                                aria-label="Choisir la carte {{ (int) $i + 1 }}"
                            >
                                <div class="w-[min(28vw,9.5rem)] aspect-[2/3] overflow-hidden rounded-2xl bg-white">
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
        };

        // Active index shared.
        let activeIndex = 0;

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

        const getCardBtnByIndex = (selector, idx) => {
            return root.querySelector(`${selector}[data-index="${idx}"]`);
        };

        const syncActiveCardPanels = () => {
            const anyBtn = getCardBtnByIndex('[data-card-thumb]', activeIndex) || getCardBtnByIndex('[data-card-fan]', activeIndex);
            if (!anyBtn) return;

            const name = anyBtn.getAttribute('data-name') || '';
            const img = anyBtn.getAttribute('data-img') || '';
            const reversed = anyBtn.getAttribute('data-reversed') === '1';
            let kws = [];
            try {
                kws = JSON.parse(anyBtn.getAttribute('data-kws') || '[]');
            } catch (e) {}

            root.querySelectorAll('[data-active-name]').forEach((el) => { el.textContent = name; });
            root.querySelectorAll('[data-active-img]').forEach((el) => {
                el.src = img;
                el.alt = name;
                el.style.transform = reversed ? 'rotate(180deg)' : 'none';
            });
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

        const zoomActiveBtn = root.querySelector('[data-zoom-active]');
        if (zoomActiveBtn) {
            zoomActiveBtn.addEventListener('click', () => {
                const btn = getCardBtnByIndex('[data-card-thumb]', activeIndex) || getCardBtnByIndex('[data-card-fan]', activeIndex);
                openModal(btn);
            });
        }

        // Fan layout
        const spread = root.querySelector('[data-spread]');
        const fanButtons = Array.from(root.querySelectorAll('[data-card-fan]'));

        const baseLayout = (n) => {
            if (n === 3) {
                return {
                    angles: [-14, 0, 14],
                    xs: [-80, 0, 80],
                    ys: [18, 0, 18],
                };
            }
            return {
                angles: [-18, -9, 0, 9, 18],
                xs: [-150, -75, 0, 75, 150],
                ys: [32, 18, 0, 18, 32],
            };
        };

        const layoutFan = () => {
            if (!spread || fanButtons.length === 0) return;
            if (!canUseRituelNow()) return;

            const rect = spread.getBoundingClientRect();
            const pad = 16;
            const cardRect = fanButtons[0].getBoundingClientRect();
            const cardW = cardRect.width || 120;
            const n = fanButtons.length;
            const base = baseLayout(n);
            const maxAbsX = Math.max(...base.xs.map((v) => Math.abs(v))) || 1;
            const usableHalf = Math.max(0, (rect.width / 2) - (cardW / 2) - pad);
            const k = Math.min(1, usableHalf / maxAbsX);

            fanButtons.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const angle = (base.angles[idx] || 0);
                const x = (base.xs[idx] || 0) * k;
                const y = (base.ys[idx] || 0) * k;
                const isActive = idx === activeIndex;

                const scale = isActive ? 1.08 : 0.98;
                const shadow = isActive ? '0 10px 28px rgba(15,23,42,0.16)' : '0 6px 16px rgba(15,23,42,0.10)';
                btn.style.zIndex = String(isActive ? 20 : 10 + idx);
                btn.style.boxShadow = shadow;
                btn.style.filter = isActive ? 'none' : 'saturate(0.92) contrast(0.98)';
                btn.style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px) rotate(${angle}deg) scale(${scale})`;
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
    })();
    </script>
@endif
