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
        $fanHeight = 'clamp(240px, 64vw, 280px)';
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
                        <div class="relative h-full w-full overflow-hidden rounded-2xl border border-black/10 bg-white" data-carousel-scene>

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
                                    class="absolute left-1/2 top-1/2 rounded-2xl bg-transparent shadow-sm transition-[transform,filter,box-shadow,opacity] duration-300 ease-out"
                                    style="{{ $fanCardSize }}; transform: translate(-50%, -50%) scale(0.96); opacity: 0;"
                                    data-card-carousel
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
        const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
            const anyBtn = getCardBtnByIndex('[data-card-carousel]', activeIndex);
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
            applyCarouselLayout(activeIndex, { instant: false, stepMode: 'final' });
        };

        const scene = root.querySelector('[data-carousel-scene]');
        const cardButtons = Array.from(root.querySelectorAll('[data-card-carousel]'));

        const getCardSize = () => {
            const rect = cardButtons[0]?.getBoundingClientRect();
            return {
                w: rect?.width || 120,
                h: rect?.height || 180,
            };
        };

        const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

        const computeStepX = (mode, centerIdx, visibleUntil) => {
            const sceneRect = scene?.getBoundingClientRect();
            const sceneW = sceneRect?.width || 360;
            const pad = 18;
            const { w: cardW } = getCardSize();
            const halfAvail = Math.max(0, (sceneW / 2) - pad - (cardW / 2));

            // How many cards exist on each side of the center for the *currently visible* set.
            const leftCount = Math.max(0, centerIdx);
            const rightCount = Math.max(0, visibleUntil - centerIdx);
            const maxSide = Math.max(leftCount, rightCount);

            // If only one card (or no side), spacing can be any reasonable default.
            if (maxSide === 0) {
                return mode === 'deal'
                    ? clamp(cardW * 0.92, 92, 170)
                    : clamp(cardW * 0.64, 70, 130);
            }

            // Ensure the farthest visible card stays inside the scene.
            const fitStep = halfAvail / maxSide;

            // Target aesthetics.
            const target = mode === 'deal'
                ? clamp(cardW * 0.92, 92, 170)
                : clamp(cardW * 0.64, 70, 130);

            return clamp(Math.min(target, fitStep), 56, target);
        };

        const applyCarouselLayout = (centerIdx, opts = {}) => {
            const { instant = false, stepMode = 'final', visibleUntil = (count - 1) } = opts;
            const stepX = computeStepX(stepMode, centerIdx, visibleUntil);

            cardButtons.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const isVisible = idx <= visibleUntil;
                const rel = idx - centerIdx;

                btn.style.transitionDuration = instant ? '0ms' : (stepMode === 'deal' ? '260ms' : '320ms');
                btn.style.transitionTimingFunction = 'cubic-bezier(.2,.9,.2,1)';

                if (!isVisible) {
                    btn.style.opacity = '0';
                    btn.style.pointerEvents = 'none';
                    btn.style.zIndex = '0';
                    btn.style.transform = 'translate(-50%, -50%) scale(0.96)';
                    return;
                }

                const x = rel * stepX;
                const rot = clamp(rel * 2.2, -10, 10);
                const isActive = idx === centerIdx;
                const scale = isActive ? 1.06 : 1.0;
                const y = isActive ? -8 : 0;
                const z = isActive ? 500 : (200 - Math.abs(rel) * 10);

                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                btn.style.zIndex = String(z);
                btn.style.filter = isActive ? 'none' : 'saturate(0.94) contrast(0.99)';
                btn.style.transformOrigin = '50% 50%';
                btn.style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px) rotate(${rot}deg) scale(${scale})`;
            });
        };

        // Tap any card to focus it.
        cardButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                activeIndex = idx;
                syncActiveCardPanels();
                applyCarouselLayout(activeIndex, { instant: false, stepMode: 'final', visibleUntil: count - 1 });
            });
        });

        // Swipe navigation.
        if (scene) {
            let startX = null;
            let startY = null;
            scene.addEventListener('pointerdown', (e) => {
                startX = e.clientX;
                startY = e.clientY;
            });
            scene.addEventListener('pointerup', (e) => {
                if (startX == null || startY == null) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                startX = null;
                startY = null;
                if (Math.abs(dx) < 30 || Math.abs(dx) < Math.abs(dy)) return;
                const next = Math.max(0, Math.min(count - 1, activeIndex + (dx < 0 ? 1 : -1)));
                activeIndex = next;
                syncActiveCardPanels();
                applyCarouselLayout(activeIndex, { instant: false, stepMode: 'final', visibleUntil: count - 1 });
            });
        }

        const ro = (window.ResizeObserver && scene) ? new ResizeObserver(() => {
            applyCarouselLayout(activeIndex, { instant: true, stepMode: 'final', visibleUntil: count - 1 });
        }) : null;
        if (ro && scene) ro.observe(scene);

        window.addEventListener('resize', () => {
            applyCarouselLayout(activeIndex, { instant: true, stepMode: 'final', visibleUntil: count - 1 });
        });

        const runDealAnimation = () => {
            // Deal sequence: 0 shows center, then 1 shows center (0 shifts left), etc.
            // After last card is dealt, we re-center the whole group on the middle card.
            const finalCenter = Math.floor(count / 2);

            // First frame: only card 0 visible at center.
            activeIndex = 0;
            syncActiveCardPanels();
            applyCarouselLayout(0, { instant: true, stepMode: 'deal', visibleUntil: 0 });

            if (prefersReducedMotion) {
                activeIndex = finalCenter;
                syncActiveCardPanels();
                applyCarouselLayout(finalCenter, { instant: true, stepMode: 'final', visibleUntil: count - 1 });
                return;
            }

            let step = 1;
            const dealNext = () => {
                if (step >= count) {
                    // Focus slide: center the group on the middle card.
                    setTimeout(() => {
                        activeIndex = finalCenter;
                        syncActiveCardPanels();
                        applyCarouselLayout(finalCenter, { instant: false, stepMode: 'final', visibleUntil: count - 1 });
                    }, 320);
                    return;
                }

                activeIndex = step;
                syncActiveCardPanels();
                applyCarouselLayout(step, { instant: false, stepMode: 'deal', visibleUntil: step });
                step += 1;
                setTimeout(dealNext, 360);
            };

            setTimeout(dealNext, 260);
        };

        // Init
        requestAnimationFrame(() => runDealAnimation());
    })();
    </script>
@endif
