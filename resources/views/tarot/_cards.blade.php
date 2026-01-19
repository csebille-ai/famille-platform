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
    @php
        $fanHeight = $N === 5 ? 'clamp(260px, 70vw, 320px)' : 'clamp(240px, 64vw, 280px)';
        $fanCardSize = $N === 5
            ? 'width: clamp(112px, 30vw, 130px); height: clamp(168px, 45vw, 195px);'
            : 'width: clamp(120px, 34vw, 140px); height: clamp(180px, 51vw, 210px);';
    @endphp
    <div id="{{ $idPrefix }}-cards-ui" class="space-y-4" data-tarot-cards-ui data-count="{{ $N }}" data-supports-rituel="{{ $supportsRituel ? '1' : '0' }}">
        @if(!$supportsRituel)
            <div class="fam-card p-4 text-sm text-slate-600">
                Rituel disponible uniquement pour 3 ou 5 cartes.
            </div>
        @else
            <div class="fam-card p-4">
                <div class="-mx-4">
                    <div class="w-full" style="height: {{ $fanHeight }};">
                        <div class="relative h-full w-full overflow-visible" data-spread>
                            <div class="absolute inset-0 hidden pointer-events-none" data-rituel-debug>
                                <div class="absolute left-4 top-4 h-24 w-16 rounded-xl bg-rose-300/80 ring-2 ring-rose-500/60" style="z-index: 1"></div>
                                <div class="absolute left-24 top-10 h-24 w-16 rounded-xl bg-emerald-300/80 ring-2 ring-emerald-500/60" style="z-index: 2"></div>
                                <div class="absolute left-44 top-16 h-24 w-16 rounded-xl bg-sky-300/80 ring-2 ring-sky-500/60" style="z-index: 3"></div>
                            </div>

                            @foreach($cards as $i => $c)
                                @php
                                    $file = (string) ($c['file'] ?? '');
                                    $name = (string) ($c['name'] ?? '');
                                    $slug = (string) ($c['slug'] ?? '');
                                    $n = isset($c['n']) ? (int) $c['n'] : null;
                                    $reversed = !empty($c['reversed']);
                                    $orientation = (string) ($c['orientation'] ?? ($reversed ? 'reversed' : 'upright'));
                                    $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';
                                    $kws = $normalizeKeywords($c['keywords'] ?? '');
                                @endphp
                                <button
                                    type="button"
                                    class="absolute left-1/2 top-[90%] origin-bottom rounded-2xl bg-transparent shadow-sm transition-[transform,filter,box-shadow] duration-200 ease-out"
                                    style="{{ $fanCardSize }}"
                                    data-card-fan
                                    data-index="{{ (int) $i }}"
                                    data-name="{{ e($name) }}"
                                    data-slug="{{ e($slug) }}"
                                    data-n="{{ $n === null ? '' : (string) $n }}"
                                    data-orientation="{{ e($orientation) }}"
                                    data-reversed="{{ $reversed ? '1' : '0' }}"
                                    data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
                                    aria-label="Choisir la carte {{ (int) $i + 1 }}"
                                >
                                    <div class="h-full w-full overflow-hidden rounded-2xl bg-transparent">
                                        @if($img !== '')
                                            <img
                                                src="{{ $img }}"
                                                alt=""
                                                class="h-full w-full object-contain bg-transparent"
                                                style="transform: {{ $reversed ? 'rotate(180deg) scale(1.04)' : 'scale(1.04)' }}; clip-path: inset(0% 2.8%);"
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
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-900 truncate" data-active-name></div>
                        <div class="mt-2 flex items-center gap-2 flex-wrap" data-active-kws></div>
                        <div class="mt-3 flex items-center gap-2 flex-wrap" data-active-attrs></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
    (() => {
        const root = document.getElementById(@json($idPrefix . '-cards-ui'));
        if (!root) return;

        const supportsRituel = root.getAttribute('data-supports-rituel') === '1';
        const count = Number(root.getAttribute('data-count') || '0');
        const DEBUG_RITUEL = (() => {
            try {
                return new URLSearchParams(window.location.search).has('debugRituel');
            } catch (e) {
                return false;
            }
        })();

        // Active index shared.
        let activeIndex = 0;

        if (!supportsRituel || !(count === 3 || count === 5)) return;

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

        const renderAttrs = (container, attrs) => {
            if (!container) return;
            container.innerHTML = '';
            const items = [];

            const n = attrs?.n;
            const orientation = (attrs?.orientation || '').toLowerCase();
            const slug = attrs?.slug || '';

            if (Number.isFinite(n)) items.push(`Arcane #${n}`);
            if (orientation) {
                items.push(orientation === 'reversed' ? 'Renversée' : 'Droite');
            }
            if (slug) items.push(slug);

            items.forEach((t) => {
                const chip = document.createElement('span');
                chip.className = 'fam-chip text-xs';
                chip.textContent = t;
                container.appendChild(chip);
            });
        };

        const getCardBtnByIndex = (selector, idx) => root.querySelector(`${selector}[data-index="${idx}"]`);

        const syncActiveCardPanels = () => {
            const anyBtn = getCardBtnByIndex('[data-card-fan]', activeIndex);
            if (!anyBtn) return;

            const name = anyBtn.getAttribute('data-name') || '';
            const slug = anyBtn.getAttribute('data-slug') || '';
            const orientation = anyBtn.getAttribute('data-orientation') || '';
            const nRaw = anyBtn.getAttribute('data-n');
            const n = (nRaw === null || nRaw === '') ? null : Number(nRaw);
            let kws = [];
            try {
                kws = JSON.parse(anyBtn.getAttribute('data-kws') || '[]');
            } catch (e) {}

            root.querySelectorAll('[data-active-name]').forEach((el) => { el.textContent = name; });
            root.querySelectorAll('[data-active-kws]').forEach((el) => renderKeywords(el, kws));
            root.querySelectorAll('[data-active-attrs]').forEach((el) => renderAttrs(el, { n, orientation, slug }));
        };

        const setActiveIndex = (idx) => {
            if (!Number.isFinite(idx)) return;
            const next = Math.max(0, Math.min(count - 1, idx));
            activeIndex = next;
            syncActiveCardPanels();
            layoutFan();
        };

        root.querySelectorAll('[data-card-fan]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                // Rituel: tap = focus/zoom in place (no modal).
                setActiveIndex(idx);
            });
        });

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
                    stepAngle: 15,
                    stepX: 16,
                    stepY: 10,
                    scaleDrop: 0.04,
                };
            }
            if (n === 5) {
                return {
                    stepAngle: 10,
                    stepX: 20,
                    stepY: 9,
                    scaleDrop: 0.035,
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

            const rect = spread.getBoundingClientRect();
            const pad = 16;
            const centerX = rect.width / 2;
            const baselineY = rect.height * 0.90;
            const minTop = rect.height * 0.10;

            // Use computed size from the first card (width/height are explicitly set in markup).
            const cardRect = fanButtons[0].getBoundingClientRect();
            const cardW = cardRect.width || 120;
            const cardH = cardRect.height || 180;
            const n = fanButtons.length;

            if (n === 5) {
                // 5-card fan: near half-circle with a shared bottom-left anchor point.
                const anchorX = pad;
                const anchorY = rect.height * 0.92;
                // Requested opening: ~160 degrees.
                const minAngle = -30;
                const maxAngle = 130;
                const step = (maxAngle - minAngle) / (n - 1);
                const activeBoost = 0.08;

                fanButtons.forEach((btn) => {
                    const idx = Number(btn.getAttribute('data-index') || '0');
                    const angle = minAngle + (idx * step);
                    const isActive = idx === activeIndex;
                    const t = idx / (n - 1);
                    const scaleBase = 1 - (Math.abs(t - 0.55) * 0.035);
                    const scale = isActive ? (scaleBase + activeBoost) : scaleBase;
                    const shadow = isActive ? '0 16px 40px rgba(15,23,42,0.22)' : '0 6px 16px rgba(15,23,42,0.10)';

                    btn.style.zIndex = String(isActive ? 500 : (200 - idx));
                    btn.style.boxShadow = shadow;
                    btn.style.filter = isActive ? 'none' : 'saturate(0.92) contrast(0.98)';
                    btn.style.left = `${anchorX}px`;
                    btn.style.top = `${anchorY}px`;
                    btn.style.transformOrigin = '0% 100%';
                    btn.style.transform = `translate(0, -100%) rotate(${angle}deg) scale(${scale})`;

                    if (DEBUG_RITUEL) {
                        btn.style.outline = isActive ? '2px solid rgba(14,165,160,0.55)' : '1px dashed rgba(2,6,23,0.28)';
                        btn.style.outlineOffset = '2px';
                    }
                });

                return;
            }
            const base = baseLayout(n);
            const maxX = Math.max(0, (rect.width / 2) - (cardW / 2) - pad);

            // Fixed fan: positions do NOT change when active card changes.
            const center = (n - 1) / 2;
            const tMax = (n - 1) / 2;

            // Scale X/angles down if the spread would overflow.
            const maxAbsX = Math.abs(tMax * base.stepX) || 1;
            const k = Math.min(1, maxX / maxAbsX);

            const activeBoost = 0.08;

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

                // Clamp Y so the card top doesn't go above minTop.
                let y = rawY;
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
                const scale = isActive ? (scaleBase + activeBoost) : scaleBase;
                const shadow = isActive ? '0 16px 40px rgba(15,23,42,0.22)' : '0 6px 16px rgba(15,23,42,0.10)';
                // Active card comes on top so the zoom is visible.
                btn.style.zIndex = String(isActive ? 500 : (200 - idx));
                btn.style.boxShadow = shadow;
                btn.style.filter = isActive ? 'none' : 'saturate(0.92) contrast(0.98)';
                btn.style.transformOrigin = '50% 100%';
                btn.style.left = `${centerX}px`;
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
            layoutFan();
        }) : null;
        if (ro && spread) ro.observe(spread);

        window.addEventListener('resize', () => {
            layoutFan();
        });

        // Init
        setActiveIndex(0);
        requestAnimationFrame(() => layoutFan());
    })();
    </script>
@endif
